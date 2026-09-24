<?php
declare(strict_types=1);

namespace BBTChat\Provider;

/**
 * Answers without calling anything, by quoting the retrieved passages back.
 *
 * This is not a language model and does not pretend to be one - it exists so
 * the index, retrieval, REST endpoint, rate limiting and chat widget can all be
 * exercised end to end at zero cost and with no API key. If the mock returns
 * the right passages for a question, the only thing left for the real model to
 * do is phrase them.
 */
final class MockProvider implements Provider
{
    public function name(): string
    {
        return 'Mock (no API key, for local testing)';
    }

    public function answer(string $system, array $messages): Answer
    {
        $last = '';
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'user') {
                $last = $message['content'];
                break;
            }
        }

        preg_match_all('/<source id="\d+" url="([^"]+)" page="([^"]*)">\s*(.*?)\s*<\/source>/s', $last, $m, PREG_SET_ORDER);

        if ($m === []) {
            return new Answer(
                "[mock] No page content was retrieved for this question, so the real model would "
                . "decline to answer and point the visitor at admissions."
            );
        }

        $lines = ['[mock] The model would answer from these passages:'];
        foreach ($m as $i => $source) {
            $excerpt = trim(preg_replace('/\s+/u', ' ', $source[3]) ?? '');
            $lines[] = sprintf('%d. %s — %s', $i + 1, $source[2], $source[1]);
            $lines[] = '   ' . mb_substr($excerpt, 0, 220) . (mb_strlen($excerpt) > 220 ? '…' : '');
        }

        return new Answer(
            implode("\n", $lines),
            ['input_tokens' => 0, 'output_tokens' => 0],
        );
    }
}
