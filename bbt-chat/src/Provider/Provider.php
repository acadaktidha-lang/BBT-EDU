<?php
declare(strict_types=1);

namespace BBTChat\Provider;

/**
 * A source of answers.
 *
 * Everything upstream of this - indexing, retrieval, prompt assembly, the
 * widget - works without an API key, so the whole pipeline can be built and
 * tested before any Claude credentials exist.
 */
interface Provider
{
    /**
     * @param  string                                     $system   cacheable instructions + facts
     * @param  list<array{role:string,content:string}>    $messages conversation, oldest first
     * @return Answer
     */
    public function answer(string $system, array $messages): Answer;

    /** Human-readable name for the admin screen and logs. */
    public function name(): string;
}
