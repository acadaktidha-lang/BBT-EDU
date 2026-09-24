<?php
declare(strict_types=1);

namespace BBTChat\WordPress;

use BBTChat\Answerer;
use BBTChat\Provider\ClaudeProvider;
use BBTChat\Provider\MockProvider;
use BBTChat\Provider\Provider;
use BBTChat\Provider\ProviderException;
use BBTChat\Retriever;

/**
 * The one public endpoint: POST /wp-json/bbt-chat/v1/ask
 *
 * It has to be open to anonymous visitors, which means it is also open to
 * anyone who wants to spend the site's Anthropic credit. Two ceilings guard
 * that: a per-visitor hourly limit, and a site-wide daily limit that caps the
 * worst case no matter how many visitors are involved.
 */
final class RestController
{
    private const NAMESPACE = 'bbt-chat/v1';
    private const MAX_QUESTION_CHARS = 500;

    public function __construct(
        private readonly Settings $settings,
        private readonly IndexStore $store,
    ) {
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/ask', [
            'methods'             => 'POST',
            'callback'            => [$this, 'ask'],
            'permission_callback' => '__return_true',
            'args'                => [
                'question' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => static fn ($value): string => sanitize_textarea_field((string) $value),
                ],
                'history' => [
                    'required' => false,
                    'type'     => 'array',
                    'default'  => [],
                ],
            ],
        ]);
    }

    public function ask(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->settings->get('enabled')) {
            return $this->error('The assistant is switched off.', 503);
        }

        $question = trim((string) $request->get_param('question'));
        if ($question === '') {
            return $this->error('Please type a question.', 400);
        }
        if (mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            return $this->error('That question is too long. Please shorten it to a couple of sentences.', 400);
        }

        if (!$this->withinLimits()) {
            return $this->error(
                'The assistant is busy right now. Please message us on WhatsApp at 0326-0188811.',
                429
            );
        }

        $index = $this->store->index();
        if ($index === null) {
            return $this->error('The assistant is still being set up. Please try again shortly.', 503);
        }

        try {
            $answerer = new Answerer(
                new Retriever($index),
                $this->provider(),
                $this->settings->facts()
            );
            $result = $answerer->ask($question, $this->history($request));
        } catch (ProviderException $e) {
            // The real reason goes to the log; the visitor gets a usable next step.
            error_log('[bbt-chat] ' . $e->getMessage());

            return $this->error(
                'I could not answer that just now. Please message us on WhatsApp at 0326-0188811.',
                502
            );
        }

        $answer = $result['answer'];
        if ($answer->wasRefused() || trim($answer->text) === '') {
            return new \WP_REST_Response([
                'answer'  => 'I can\'t help with that one. For anything about courses or admission, '
                    . 'message us on WhatsApp at 0326-0188811.',
                'sources' => [],
            ], 200);
        }

        return new \WP_REST_Response([
            'answer'  => $answer->text,
            'sources' => $result['sources'],
        ], 200);
    }

    private function provider(): Provider
    {
        if ($this->settings->get('provider') !== 'claude') {
            return new MockProvider();
        }

        return new ClaudeProvider(
            (string) $this->settings->get('api_key'),
            (string) $this->settings->get('model'),
            (string) $this->settings->get('effort'),
            (int) $this->settings->get('max_tokens'),
            // Reuse WordPress's HTTP stack: its CA bundle, proxy config and
            // timeouts are already correct for this host.
            static function (string $url, array $headers, string $body): array {
                $response = wp_remote_post($url, [
                    'headers' => $headers,
                    'body'    => $body,
                    'timeout' => 60,
                ]);

                if (is_wp_error($response)) {
                    throw new ProviderException($response->get_error_message());
                }

                return [
                    'status' => (int) wp_remote_retrieve_response_code($response),
                    'body'   => (string) wp_remote_retrieve_body($response),
                ];
            }
        );
    }

    /**
     * @return list<array{role:string,content:string}>
     */
    private function history(\WP_REST_Request $request): array
    {
        $raw     = (array) $request->get_param('history');
        $history = [];
        foreach (array_slice($raw, -6) as $turn) {
            $role    = (string) ($turn['role'] ?? '');
            $content = trim((string) ($turn['content'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $history[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
        }

        return $history;
    }

    /**
     * Per-visitor hourly cap plus a site-wide daily cap. Transients are good
     * enough here: losing a counter to a cache flush costs a few extra
     * questions, and needs no table.
     */
    private function withinLimits(): bool
    {
        $perIp = (int) $this->settings->get('questions_per_ip');
        $perDay = (int) $this->settings->get('questions_per_day');

        $ipKey = 'bbt_chat_ip_' . hash('sha256', $this->clientIp() . wp_salt());
        $used  = (int) get_transient($ipKey);
        if ($used >= $perIp) {
            return false;
        }

        $dayKey = 'bbt_chat_day_' . gmdate('Ymd');
        $today  = (int) get_transient($dayKey);
        if ($today >= $perDay) {
            return false;
        }

        set_transient($ipKey, $used + 1, HOUR_IN_SECONDS);
        set_transient($dayKey, $today + 1, DAY_IN_SECONDS);

        return true;
    }

    /** Only ever hashed, never stored or logged in the clear. */
    private function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
            $value = $_SERVER[$header] ?? '';
            if (!is_string($value) || $value === '') {
                continue;
            }
            $first = trim(explode(',', $value)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return 'unknown';
    }

    private function error(string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response(['message' => $message], $status);
    }
}
