<?php
declare(strict_types=1);

namespace BBTChat\Provider;

/** What a provider returns: the reply, plus what it cost and how it ended. */
final class Answer
{
    /**
     * @param array<string,int> $usage token counts as reported by the provider
     */
    public function __construct(
        public readonly string $text,
        public readonly array $usage = [],
        public readonly string $stopReason = 'end_turn',
        public readonly ?string $refusalCategory = null,
    ) {
    }

    public function wasRefused(): bool
    {
        return $this->stopReason === 'refusal';
    }

    /** Cache hits are the difference between a cheap bot and an expensive one, so surface them. */
    public function cacheReadTokens(): int
    {
        return $this->usage['cache_read_input_tokens'] ?? 0;
    }
}
