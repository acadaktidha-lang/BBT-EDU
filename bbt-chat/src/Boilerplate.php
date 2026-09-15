<?php
declare(strict_types=1);

namespace BBTChat;

/**
 * Removes the navigation and footer text that every page repeats.
 *
 * Each BBT page is a standalone HTML document with its own header and footer,
 * so the menu appears 49 times in the corpus. Left in, it out-frequencies the
 * real content and the retriever answers every question with the site menu.
 *
 * Detection is per line rather than per passage: the menu merges into a
 * different neighbouring passage on each page, so whole passages rarely repeat
 * byte-for-byte, but the individual menu lines always do.
 */
final class Boilerplate
{
    /** A line on at least this share of pages is chrome, not content. */
    private const PAGE_SHARE = 0.5;

    /**
     * Lines longer than this are kept regardless. A genuine sentence repeated
     * across pages (a mission statement, a guarantee) is content worth keeping;
     * menu items and button labels are short.
     */
    private const KEEP_IF_LONGER_THAN = 90;

    /**
     * @param  array<string,string> $textsByPage page id => extracted text
     * @return array<string,string> same keys, with repeated chrome removed
     */
    public static function strip(array $textsByPage): array
    {
        $common = self::commonLines($textsByPage);

        $out = [];
        foreach ($textsByPage as $id => $text) {
            $kept = [];
            foreach (explode("\n", $text) as $line) {
                if (!isset($common[self::fingerprint($line)])) {
                    $kept[] = $line;
                }
            }
            $out[$id] = Text::normalise(implode("\n", $kept));
        }

        return $out;
    }

    /**
     * @param  array<string,string> $textsByPage
     * @return array<string,true>   fingerprints of lines that count as chrome
     */
    public static function commonLines(array $textsByPage): array
    {
        $pageCount = max(count($textsByPage), 1);
        $threshold = max(3, (int) ceil($pageCount * self::PAGE_SHARE));

        /** @var array<string,array<string,true>> $seenOn */
        $seenOn = [];
        foreach ($textsByPage as $id => $text) {
            foreach (explode("\n", $text) as $line) {
                if (trim($line) === '' || mb_strlen($line) > self::KEEP_IF_LONGER_THAN) {
                    continue;
                }
                $seenOn[self::fingerprint($line)][$id] = true;
            }
        }

        $common = [];
        foreach ($seenOn as $fingerprint => $pages) {
            if (count($pages) >= $threshold) {
                $common[$fingerprint] = true;
            }
        }

        return $common;
    }

    /**
     * The chrome, reassembled once, in the order one page presents it.
     *
     * The footer carries the phone numbers, email and opening hours - the most
     * asked-about facts on the site. Dropping them from all 49 pages would make
     * the bot unable to answer "what's your number", so they are indexed once as
     * a single site-wide document instead.
     *
     * @param array<string,string> $textsByPage
     */
    public static function sharedBlock(array $textsByPage): string
    {
        $common = self::commonLines($textsByPage);
        if ($common === []) {
            return '';
        }

        // Use whichever page carries the most chrome as the template, so the
        // menu and footer come out in their natural reading order.
        $best      = '';
        $bestCount = -1;
        foreach ($textsByPage as $text) {
            $count = 0;
            foreach (explode("\n", $text) as $line) {
                if (isset($common[self::fingerprint($line)])) {
                    $count++;
                }
            }
            if ($count > $bestCount) {
                $bestCount = $count;
                $best      = $text;
            }
        }

        $lines    = [];
        $previous = null;
        foreach (explode("\n", $best) as $line) {
            $fingerprint = self::fingerprint($line);
            if (!isset($common[$fingerprint]) || $fingerprint === $previous) {
                continue;
            }
            $lines[]  = trim($line);
            $previous = $fingerprint;
        }

        return Text::normalise(implode("\n", $lines));
    }

    private static function fingerprint(string $line): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $line) ?? $line));
    }
}
