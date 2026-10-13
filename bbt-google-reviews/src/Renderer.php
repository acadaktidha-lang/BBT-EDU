<?php
/**
 * Renders review cards in the home page's own markup (the .x-rv cards the
 * slider in index.html styles), so swapping static cards for live ones
 * changes nothing visually.
 */

declare(strict_types=1);

namespace BBTGoogleReviews;

final class Renderer
{
    private const STAR = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.6 5.6 6.1.7-4.5 4.2 1.2 6L10 15l-5.4 3 1.2-6L1.3 7.8l6.1-.7z"/></svg>';
    private const G = '<svg class="x-g" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.5z"/></svg>';

    /** @param list<array{name:string,stars:float,photo:string,text:string,real_photo:bool}> $reviews */
    public static function cards(array $reviews): string
    {
        return implode("\n", array_map([self::class, 'card'], $reviews));
    }

    /** @param array{name:string,stars:float,photo:string,text:string,real_photo:bool} $r */
    public static function card(array $r): string
    {
        $name = self::e($r['name'] !== '' ? $r['name'] : 'Google user');
        $avatar = $r['real_photo']
            ? '<img class="x-rv-av" src="' . self::e(self::sized($r['photo'], 96)) . '" alt="" width="44" height="44" loading="lazy" referrerpolicy="no-referrer">'
            : '<span class="x-rv-av" aria-hidden="true">' . self::e(self::initials($r['name'])) . '</span>';
        $n = (int) round($r['stars']);
        $stars = '<div class="x-rv-stars" role="img" aria-label="' . $n . ' out of 5 stars">' . str_repeat(self::STAR, $n) . '</div>';
        $long = mb_strlen($r['text']) > 220;
        $body = '<blockquote class="x-rv-text' . ($long ? ' is-long' : '') . '">' . nl2br(self::e($r['text']), false) . '</blockquote>';
        if ($long) {
            $body .= '<button type="button" class="x-rv-more" onclick="var q=this.previousElementSibling;q.classList.toggle(\'open\');this.textContent=q.classList.contains(\'open\')?\'Show less\':\'Read more\'">Read more</button>';
        }
        return '      <figure class="x-rv">' . "\n"
            . '        <figcaption class="x-rv-who">' . $avatar . '<div><b>' . $name . '</b><span>Google review</span></div>' . self::G . '</figcaption>' . "\n"
            . '        ' . $stars . "\n"
            . '        ' . $body . "\n"
            . '      </figure>';
    }

    /** Google avatar URLs end in a size directive such as =w40-h40-c-rp-mo-br100. */
    public static function sized(string $url, int $px): string
    {
        return preg_match('#googleusercontent\.com/#', $url)
            ? (preg_replace('/=[a-z0-9-]+$/i', '', $url) ?? $url) . '=s' . $px . '-c'
            : $url;
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $out = '';
        foreach (array_slice(array_filter($words), 0, 2) as $w) {
            $out .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        return $out !== '' ? $out : '?';
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
