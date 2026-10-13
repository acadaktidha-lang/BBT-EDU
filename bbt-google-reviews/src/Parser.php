<?php
/**
 * Turns the HTML of a Trustindex Google widget into plain review records.
 *
 * Plain PHP, no WordPress calls, so it can be tested from the CLI. It keys on
 * markup that is the same in every Trustindex layout (the per-review
 * data-time attribute, .ti-name, .ti-profile-img, the star images and the
 * R-CONTENT comments), not on layout-specific wrappers.
 */

declare(strict_types=1);

namespace BBTGoogleReviews;

final class Parser
{
    /**
     * @return list<array{name:string,time:int,stars:float,photo:string,text:string}>
     */
    public static function parse(string $html): array
    {
        $parts = preg_split('/(?=<div[^>]*\sdata-time="\d+")/', $html) ?: [];
        array_shift($parts);

        $reviews = [];
        foreach ($parts as $item) {
            if (!preg_match('/data-time="(\d+)"/', $item, $t)) {
                continue;
            }
            $name = preg_match('/class="ti-name"[^>]*>\s*(.*?)\s*<\/div>/s', $item, $m) ? self::text($m[1]) : '';
            $photo = preg_match('/class="ti-profile-img"[^>]*>\s*<img[^>]*?\ssrc="([^"]+)"/s', $item, $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
            $full = preg_match_all('#/star/f\.svg#', $item);
            $half = preg_match_all('#/star/h\.svg#', $item);
            $text = preg_match('/<!-- R-CONTENT -->(.*?)<!-- R-CONTENT -->/s', $item, $m) ? self::text($m[1]) : '';

            if ($name === '' && $text === '') {
                continue;
            }
            $reviews[] = [
                'name'  => $name,
                'time'  => (int) $t[1],
                'stars' => $full + 0.5 * $half,
                'photo' => $photo,
                'text'  => $text,
            ];
        }
        return $reviews;
    }

    private static function text(string $html): string
    {
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
