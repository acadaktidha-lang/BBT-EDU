<?php
/**
 * Picks which reviews go on the site.
 *
 * Rules, in order: enough stars, has text, not on the block list, then the
 * photo rule ('only' keeps reviews whose reviewer has a real profile photo,
 * 'prefer' puts those first, 'any' ignores photos), newest first, capped.
 */

declare(strict_types=1);

namespace BBTGoogleReviews;

final class Selector
{
    /**
     * @param list<array{name:string,time:int,stars:float,photo:string,text:string}> $reviews
     * @param array<string,bool> $hasPhoto photo URL => true when it is a real photo
     * @param array{min_stars:float,photo:string,exclude:list<string>,max:int} $rules
     * @return list<array{name:string,time:int,stars:float,photo:string,text:string,real_photo:bool}>
     */
    public static function select(array $reviews, array $hasPhoto, array $rules): array
    {
        $blocked = array_map(static fn(string $n): string => mb_strtolower(trim($n)), $rules['exclude']);
        $out = [];
        foreach ($reviews as $r) {
            if ($r['stars'] < $rules['min_stars'] || $r['text'] === '') {
                continue;
            }
            if (in_array(mb_strtolower(trim($r['name'])), $blocked, true)) {
                continue;
            }
            $r['real_photo'] = $r['photo'] !== '' && ($hasPhoto[$r['photo']] ?? false);
            if ($rules['photo'] === 'only' && !$r['real_photo']) {
                continue;
            }
            $out[] = $r;
        }
        usort($out, static function (array $a, array $b) use ($rules): int {
            if ($rules['photo'] === 'prefer' && $a['real_photo'] !== $b['real_photo']) {
                return $a['real_photo'] ? -1 : 1;
            }
            return $b['time'] <=> $a['time'];
        });
        return array_slice($out, 0, max(1, $rules['max']));
    }
}
