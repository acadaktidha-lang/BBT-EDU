<?php
declare(strict_types=1);

namespace BBTChat\Provider;

/**
 * Anthropic Messages API over plain HTTPS.
 *
 * Deliberately not the official PHP SDK: this ships as a WordPress plugin onto
 * a site already running Elementor and Fluent Forms, both of which bundle their
 * own Composer dependencies. Two plugins autoloading different versions of the
 * same HTTP library is one of the most common ways to take a WordPress site
 * down, and the request here is a single JSON POST. The transport is injectable
 * so WordPress can hand in wp_remote_post() and reuse its configured CA bundle,
 * proxy settings and timeouts.
 */
final class ClaudeProvider implements Provider
{
    private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /**
     * Routes a refused request to a suitable fallback model instead of
     * returning nothing. Refusals are unlikely for site Q&A, but a visitor can
     * always type something that trips a classifier, and a silent empty reply
     * in a chat bubble looks like a broken site.
     */
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /** @var (\Closure(string,array<string,string>,string):array{status:int,body:string})|null */
    private ?\Closure $transport;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-5',
        private readonly string $effort = 'low',
        private readonly int $maxTokens = 1024,
        ?callable $transport = null,
    ) {
        $this->transport = $transport === null ? null : \Closure::fromCallable($transport);
    }

    public function name(): string
    {
        return 'Claude (' . $this->model . ', effort ' . $this->effort . ')';
    }

    public function answer(string $system, array $messages): Answer
    {
        if (trim($this->apiKey) === '') {
            throw new ProviderException('No Anthropic API key is configured.');
        }

        $payload = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            // Site Q&A is grounded in retrieved text, so depth of reasoning buys
            // little; low effort keeps the reply fast and cheap.
            'output_config' => ['effort' => $this->effort],
            'fallbacks'     => 'default',
            // One cache breakpoint at the end of the system block. Everything
            // volatile - the question and the retrieved passages - lives in
            // messages, after this point, so the prefix stays byte-identical.
            'system' => [[
                'type'          => 'text',
                'text'          => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => array_map(
                static fn (array $m): array => ['role' => $m['role'], 'content' => $m['content']],
                array_values($messages)
            ),
        ];

        $headers = [
            'content-type'      => 'application/json',
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'anthropic-beta'    => self::FALLBACK_BETA,
        ];

        $response = $this->send(self::ENDPOINT, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}');
        $decoded  = json_decode($response['body'], true);

        if ($response['status'] !== 200 || !is_array($decoded)) {
            throw new ProviderException(self::describeFailure($response['status'], $decoded, $response['body']));
        }

        $stopReason = (string) ($decoded['stop_reason'] ?? 'end_turn');
        $usage      = array_map('intval', array_filter((array) ($decoded['usage'] ?? []), 'is_numeric'));

        // stop_details is populated only on a refusal - guard before reading it.
        if ($stopReason === 'refusal') {
            return new Answer(
                '',
                $usage,
                'refusal',
                (string) ($decoded['stop_details']['category'] ?? 'unknown')
            );
        }

        $text = '';
        foreach ((array) ($decoded['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return new Answer(trim($text), $usage, $stopReason);
    }

    /**
     * @param  array<string,string> $headers
     * @return array{status:int,body:string}
     */
    private function send(string $url, array $headers, string $body): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, $headers, $body);
        }

        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $formatted,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $caBundle = getenv('BBT_CA_BUNDLE');
        if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
            curl_setopt($handle, CURLOPT_CAINFO, $caBundle);
        }

        $responseBody = curl_exec($handle);
        $status       = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error        = curl_error($handle);
        curl_close($handle);

        if ($responseBody === false) {
            throw new ProviderException('Could not reach the Anthropic API: ' . $error);
        }

        return ['status' => $status, 'body' => (string) $responseBody];
    }

    private static function describeFailure(int $status, mixed $decoded, string $raw): string
    {
        $detail = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
        if ($detail === '') {
            $detail = mb_substr($raw, 0, 300);
        }

        return match (true) {
            $status === 401 => 'Anthropic rejected the API key (401). Check the key in the plugin settings.',
            $status === 403 => 'The API key is not allowed to use this model (403). ' . $detail,
            $status === 404 => 'Unknown model name (404). Check the model set in the plugin settings. ' . $detail,
            $status === 429 => 'Rate limited by Anthropic (429). ' . $detail,
            $status >= 500  => "Anthropic returned a server error ($status). " . $detail,
            default         => "Anthropic returned HTTP $status. " . $detail,
        };
    }
}
