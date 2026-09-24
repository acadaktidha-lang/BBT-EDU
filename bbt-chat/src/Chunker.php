<?php
declare(strict_types=1);

namespace BBTChat;

/**
 * Splits page HTML into passages of roughly uniform size, cut on heading
 * boundaries so a passage never straddles two unrelated topics.
 */
final class Chunker
{
    public function __construct(
        private readonly int $targetWords = 220,
        private readonly int $maxWords = 340,
        private readonly int $minWords = 25,
    ) {
    }

    /**
     * @return list<Chunk>
     */
    public function chunk(string $pageId, string $url, string $pageTitle, string $html): array
    {
        return $this->chunkText($pageId, $url, $pageTitle, Text::fromHtml($html));
    }

    /**
     * Same as chunk(), for text already extracted and de-chromed by {@see Boilerplate}.
     *
     * @return list<Chunk>
     */
    public function chunkText(string $pageId, string $url, string $pageTitle, string $text): array
    {
        $segments = $this->splitOnHeadings($text);

        $chunks   = [];
        $buffer   = [];
        $words    = 0;
        $headings = [];
        $seq      = 0;

        $flush = function () use (&$chunks, &$buffer, &$words, &$headings, &$seq, $pageId, $url, $pageTitle): void {
            if ($buffer === []) {
                return;
            }
            $body = Text::normalise(implode("\n", $buffer));
            $buffer = [];
            $words  = 0;
            if (str_word_count($body) < $this->minWords) {
                return;
            }
            $chunks[] = new Chunk($pageId . '#' . $seq++, $url, $pageTitle, $headings, $body);
        };

        foreach ($segments as $segment) {
            if ($segment['type'] === 'heading') {
                // A new heading at this level or above ends the current passage.
                $flush();
                $headings = $this->updateHeadingPath($headings, $segment['level'], $segment['text']);
                continue;
            }

            foreach ($this->splitLongBlock($segment['text']) as $block) {
                $blockWords = str_word_count($block);
                if ($words > 0 && $words + $blockWords > $this->maxWords) {
                    $flush();
                }
                $buffer[] = $block;
                $words   += $blockWords;
                if ($words >= $this->targetWords) {
                    $flush();
                }
            }
        }
        $flush();

        return $chunks;
    }

    /**
     * @return list<array{type:string,level:int,text:string}>
     */
    private function splitOnHeadings(string $text): array
    {
        $pattern = '/' . Text::HEADING_OPEN . '(\d)\|(.*?)' . Text::HEADING_CLOSE . '/su';
        $parts   = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $segments = [];
        for ($i = 0; $i < count($parts); $i++) {
            // preg_split with DELIM_CAPTURE yields: body, level, heading, body, level, heading, ...
            if ($i % 3 === 0) {
                $body = trim($parts[$i]);
                if ($body !== '') {
                    $segments[] = ['type' => 'body', 'level' => 0, 'text' => $body];
                }
                continue;
            }
            if ($i % 3 === 1) {
                $level = (int) $parts[$i];
                $title = trim($parts[$i + 1] ?? '');
                if ($title !== '') {
                    $segments[] = ['type' => 'heading', 'level' => $level, 'text' => $title];
                }
                $i++;
            }
        }

        return $segments;
    }

    /**
     * @param list<string> $path
     * @return list<string>
     */
    private function updateHeadingPath(array $path, int $level, string $heading): array
    {
        // h2 sits at depth 1, h3 at depth 2, and so on; h1 resets the trail.
        $depth = max(0, $level - 2);
        $path  = array_slice($path, 0, $depth);
        $path[] = $heading;

        return array_values($path);
    }

    /**
     * A single wall-of-text block longer than maxWords is broken on sentence
     * boundaries so one giant paragraph cannot swallow a whole chunk budget.
     *
     * @return list<string>
     */
    private function splitLongBlock(string $block): array
    {
        if (str_word_count($block) <= $this->maxWords) {
            return [$block];
        }

        $out     = [];
        $current = '';
        foreach ($this->atomicPieces($block) as $piece) {
            if ($current !== '' && str_word_count($current . ' ' . $piece) > $this->targetWords) {
                $out[]   = $current;
                $current = '';
            }
            $current = $current === '' ? $piece : $current . "\n" . $piece;
        }
        if ($current !== '') {
            $out[] = $current;
        }

        return $out;
    }

    /**
     * Smallest units we are willing to split a long block into: sentences where
     * the text has sentence punctuation, lines where it does not, and finally a
     * hard word-count cut. Link lists and footers have no full stops at all, so
     * sentence splitting alone leaves them as one indivisible wall of text.
     *
     * @return list<string>
     */
    private function atomicPieces(string $block): array
    {
        $pieces = [];
        foreach (preg_split('/\n+/u', $block, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            foreach (preg_split('/(?<=[.!?])\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
                if (str_word_count($sentence) <= $this->maxWords) {
                    $pieces[] = $sentence;
                    continue;
                }
                $words = preg_split('/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach (array_chunk($words, $this->targetWords) as $group) {
                    $pieces[] = implode(' ', $group);
                }
            }
        }

        return $pieces;
    }
}
