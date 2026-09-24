<?php
declare(strict_types=1);

namespace BBTChat;

/**
 * HTML -> plain text, and the tokenizer the BM25 index is built on.
 *
 * Deliberately free of WordPress dependencies so the whole retrieval core can
 * be built and tested from the CLI against the static repo HTML.
 */
final class Text
{
    /** Marks a heading in the intermediate text so Chunker can split on it. */
    public const HEADING_OPEN  = "\x01";
    public const HEADING_CLOSE = "\x02";

    private const DROP_TAGS = ['script', 'style', 'svg', 'noscript', 'iframe', 'form', 'select', 'option'];

    /** Words carrying no retrieval signal. Kept small on purpose: over-filtering hurts short questions. */
    private const STOPWORDS = [
        'a','an','and','are','as','at','be','but','by','can','do','does','for','from','had','has','have','he',
        'her','his','how','i','if','in','into','is','it','its','me','my','no','not','of','on','or','our','out',
        'she','so','than','that','the','their','them','then','there','these','they','this','to','up','us','was',
        'we','were','what','when','where','which','who','why','will','with','would','you','your',
    ];

    /**
     * Strip a full HTML document to readable text, keeping headings as markers.
     */
    public static function fromHtml(string $html): string
    {
        foreach (self::DROP_TAGS as $tag) {
            $html = preg_replace('#<' . $tag . '\b[^>]*>.*?</' . $tag . '>#is', ' ', $html) ?? $html;
            $html = preg_replace('#<' . $tag . '\b[^>]*/?>#i', ' ', $html) ?? $html;
        }
        $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;

        // Headings become markers so the chunker can keep a heading path.
        $html = preg_replace_callback(
            '#<h([1-6])\b[^>]*>(.*?)</h\1>#is',
            static fn (array $m): string =>
                "\n" . self::HEADING_OPEN . $m[1] . '|' . self::inline($m[2]) . self::HEADING_CLOSE . "\n",
            $html
        ) ?? $html;

        // Adjacent tags get a separator, so <span>0326-0188811</span><span>admissions@…</span>
        // does not come out of strip_tags() as one glued token.
        $html = str_replace('><', '> <', $html);

        // Block-level tags become line breaks so sentences don't run together.
        $html = preg_replace('#</(p|div|li|tr|section|article|header|footer|h[1-6]|blockquote)>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<(br|hr)\b[^>]*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<li\b[^>]*>#i', "\n- ", $html) ?? $html;

        return self::normalise(strip_tags($html));
    }

    /** Collapse an HTML fragment to a single line of text. */
    public static function inline(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', self::decode(strip_tags($html))) ?? '');
    }

    /** Collapse runs of whitespace while preserving paragraph breaks. */
    public static function normalise(string $text): string
    {
        $text = self::decode($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private static function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Lowercase, split on non-word characters, drop stopwords, light stemming.
     *
     * @return list<string>
     */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Keep digits and '+' so "c++", "3d", "1 lakh" and phone fragments survive.
        $parts = preg_split('/[^\p{L}\p{N}+#]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || in_array($part, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = self::stem($part);
        }

        return $tokens;
    }

    /**
     * Crude suffix stripper, applied repeatedly until the word stops changing
     * so that "trainings" and "training" both reach "train".
     *
     * Full Porter stemming is overkill for a 50-page corpus. Over-stemming is
     * mostly harmless here because it is applied identically to the query and
     * to the documents ("coding" and "coding" still meet, wherever they land);
     * the length guards exist to stop two *different* words colliding.
     */
    public static function stem(string $word): string
    {
        for ($pass = 0; $pass < 3; $pass++) {
            $next = self::stripSuffix($word);
            if ($next === $word) {
                break;
            }
            $word = $next;
        }

        return $word;
    }

    private static function stripSuffix(string $word): string
    {
        if (mb_strlen($word) <= 4) {
            return $word;
        }
        foreach (['ies' => 'y', 'ing' => '', 'ed' => '', 'es' => '', 's' => ''] as $suffix => $replacement) {
            if (!str_ends_with($word, $suffix)) {
                continue;
            }
            $stem = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix)) . $replacement;
            // Refuse a stem so short it is likely a different word ("coding" -> "cod").
            if (mb_strlen($stem) >= 4) {
                return $stem;
            }
        }

        return $word;
    }

    /** Rough token estimate for budgeting the prompt. Good to about +/-15% on English prose. */
    public static function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 3.7);
    }
}
