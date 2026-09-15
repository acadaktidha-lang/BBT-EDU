<?php
declare(strict_types=1);

namespace BBTChat\WordPress;

/**
 * Plugin options, with defaults that are safe on a live site.
 *
 * The widget ships disabled: installing and activating the plugin changes
 * nothing a visitor can see until someone deliberately turns it on.
 */
final class Settings
{
    public const OPTION = 'bbt_chat_settings';

    /** @var array<string,mixed> */
    private const DEFAULTS = [
        'enabled'          => false,
        'provider'         => 'mock',
        'api_key'          => '',
        'model'            => 'claude-opus-5',
        'effort'           => 'low',
        'max_tokens'       => 1024,
        'greeting'         => 'Hello. I can answer questions about BBT courses, tracks, timings and admission. What would you like to know?',
        'title'            => 'Ask BBT',
        'starters'         => "What courses do you offer?\nDo you have classes for kids?\nWhere are you located?\nHow do I enroll?",
        'facts'            => '',
        'questions_per_ip' => 20,
        'questions_per_day' => 500,
    ];

    /** @return array<string,mixed> */
    public function all(): array
    {
        $stored = get_option(self::OPTION, []);

        return array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * WordPress hands this whatever was posted, which can be a non-array if the
     * form is submitted empty, so it is typed loosely on purpose.
     */
    public function sanitize(mixed $input): array
    {
        $input   = is_array($input) ? $input : [];
        $current = $this->all();

        $clean = [
            'enabled'           => !empty($input['enabled']),
            'provider'          => in_array($input['provider'] ?? '', ['mock', 'claude'], true)
                ? $input['provider']
                : 'mock',
            'model'             => sanitize_text_field((string) ($input['model'] ?? $current['model'])),
            'effort'            => in_array($input['effort'] ?? '', ['low', 'medium', 'high'], true)
                ? $input['effort']
                : 'low',
            'max_tokens'        => max(256, min(4096, (int) ($input['max_tokens'] ?? $current['max_tokens']))),
            'title'             => sanitize_text_field((string) ($input['title'] ?? $current['title'])),
            'greeting'          => sanitize_textarea_field((string) ($input['greeting'] ?? $current['greeting'])),
            'starters'          => sanitize_textarea_field((string) ($input['starters'] ?? $current['starters'])),
            'facts'             => wp_kses_post((string) ($input['facts'] ?? $current['facts'])),
            'questions_per_ip'  => max(1, min(200, (int) ($input['questions_per_ip'] ?? $current['questions_per_ip']))),
            'questions_per_day' => max(10, min(20000, (int) ($input['questions_per_day'] ?? $current['questions_per_day']))),
        ];

        // An empty key field means "leave the stored key alone", so the saved key
        // never has to be rendered back into the page to survive a save.
        $submitted = trim((string) ($input['api_key'] ?? ''));
        $clean['api_key'] = $submitted === '' ? (string) $current['api_key'] : $submitted;

        return $clean;
    }

    /** @return list<string> */
    public function starters(): array
    {
        $lines = preg_split('/\R/', (string) $this->get('starters')) ?: [];

        return array_values(array_filter(array_map('trim', $lines)));
    }

    /**
     * Key facts sent with every question. Falls back to the bundled file so a
     * fresh install is useful before anyone edits anything.
     */
    public function facts(): string
    {
        $configured = trim((string) $this->get('facts'));
        if ($configured !== '') {
            return $configured;
        }

        $bundled = BBT_CHAT_DIR . 'data/facts.md';

        return is_readable($bundled) ? (string) file_get_contents($bundled) : '';
    }

    public function hasUsableProvider(): bool
    {
        return $this->get('provider') === 'mock'
            || trim((string) $this->get('api_key')) !== '';
    }
}
