<?php
/**
 * Plugin Name:       BBT Google Reviews
 * Description:       Keeps the home page review slider filled with the latest Google reviews, read from the Trustindex "Widgets for Google Reviews" plugin and shown in the site's own design.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Big Binary Trainings
 * License:           GPL-2.0-or-later
 * Text Domain:       bbt-google-reviews
 *
 * Additive, like bbt-chat: no database tables, no edits to page content. It
 * only swaps the cards between <!-- BBT-REVIEWS:START --> and
 * <!-- BBT-REVIEWS:END --> at render time. Deactivate it and the static cards
 * in the page show again.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Parser.php';
require_once __DIR__ . '/src/Selector.php';
require_once __DIR__ . '/src/Renderer.php';

use BBTGoogleReviews\Parser;
use BBTGoogleReviews\Renderer;
use BBTGoogleReviews\Selector;

const BBT_GR_OPTION = 'bbt_google_reviews';          // last good selection + status
const BBT_GR_PHOTOS = 'bbt_google_reviews_photos';   // photo URL => is a real photo
const BBT_GR_HOOK   = 'bbt_google_reviews_refresh';
const BBT_GR_START  = '<!-- BBT-REVIEWS:START -->';
const BBT_GR_END    = '<!-- BBT-REVIEWS:END -->';

/**
 * Rules for which reviews are shown. Change them with the
 * 'bbt_google_reviews_rules' filter rather than editing this file.
 */
function bbt_gr_rules(): array
{
    return apply_filters('bbt_google_reviews_rules', [
        'min_stars' => 4.0,
        'photo'     => 'only',          // 'only' | 'prefer' | 'any'
        'exclude'   => ['Mr Chicken'],
        'max'       => 8,
        'min_shown' => 3,               // fewer than this and the static cards stay
    ]);
}

/**
 * Google's default avatars (a coloured square with a letter) are tiny PNGs,
 * well under 3 KB at 160px; real profile photos are many times larger.
 */
function bbt_gr_is_real_photo(string $url): bool
{
    $res = wp_remote_get(Renderer::sized($url, 160), ['timeout' => 10]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        return false;
    }
    return strlen(wp_remote_retrieve_body($res)) > 6000;
}

function bbt_gr_refresh(): array
{
    $status = ['checked' => time(), 'ok' => false, 'found' => 0, 'shown' => 0, 'note' => ''];

    if (!shortcode_exists('trustindex')) {
        $status['note'] = 'Trustindex plugin is not active.';
        return bbt_gr_store($status, null);
    }
    $reviews = Parser::parse(do_shortcode('[trustindex no-registration=google]'));
    $status['found'] = count($reviews);
    if (!$reviews) {
        $status['note'] = 'Trustindex returned no reviews (widget not set up?).';
        return bbt_gr_store($status, null);
    }

    $photos = get_option(BBT_GR_PHOTOS, []);
    foreach ($reviews as $r) {
        if ($r['photo'] !== '' && !array_key_exists($r['photo'], $photos)) {
            $photos[$r['photo']] = bbt_gr_is_real_photo($r['photo']);
        }
    }
    update_option(BBT_GR_PHOTOS, $photos, false);

    $rules = bbt_gr_rules();
    $picked = Selector::select($reviews, $photos, $rules);
    $status['shown'] = count($picked);
    $status['ok'] = count($picked) >= $rules['min_shown'];
    $status['note'] = $status['ok']
        ? 'Live reviews in use.'
        : sprintf('Only %d review(s) match the rules; keeping the static cards.', count($picked));
    return bbt_gr_store($status, $status['ok'] ? $picked : null);
}

function bbt_gr_store(array $status, ?array $picked): array
{
    $prev = get_option(BBT_GR_OPTION, []);
    update_option(BBT_GR_OPTION, [
        'status'  => $status,
        // a failed refresh keeps the last good selection rather than blanking it
        'reviews' => $picked ?? ($prev['reviews'] ?? null),
    ], false);
    return $status;
}

add_filter('the_content', static function (string $content): string {
    $a = strpos($content, BBT_GR_START);
    $b = strpos($content, BBT_GR_END);
    if ($a === false || $b === false || $b < $a) {
        return $content;
    }
    $reviews = get_option(BBT_GR_OPTION, [])['reviews'] ?? null;
    if (!$reviews) {
        return $content;
    }
    $a += strlen(BBT_GR_START);
    return substr($content, 0, $a) . "\n" . Renderer::cards($reviews) . "\n      " . substr($content, $b);
}, 20);

add_filter('cron_schedules', static function (array $s): array {
    $s['bbt_six_hours'] = ['interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Every 6 hours'];
    return $s;
});
add_action(BBT_GR_HOOK, 'bbt_gr_refresh');
add_action('init', static function (): void {
    if (!wp_next_scheduled(BBT_GR_HOOK)) {
        wp_schedule_event(time() + 60, 'bbt_six_hours', BBT_GR_HOOK);
    }
});

// GET status / POST refresh, for admins only (works with an application password).
add_action('rest_api_init', static function (): void {
    $admin = static fn(): bool => current_user_can('manage_options');
    register_rest_route('bbt-google-reviews/v1', '/status', [
        'methods' => 'GET', 'permission_callback' => $admin,
        'callback' => static fn() => get_option(BBT_GR_OPTION, []),
    ]);
    register_rest_route('bbt-google-reviews/v1', '/refresh', [
        'methods' => 'POST', 'permission_callback' => $admin,
        'callback' => static fn() => ['status' => bbt_gr_refresh(), 'saved' => get_option(BBT_GR_OPTION, [])['reviews'] ?? null],
    ]);
});

register_activation_hook(__FILE__, static function (): void {
    wp_clear_scheduled_hook(BBT_GR_HOOK);
    wp_schedule_single_event(time() + 5, BBT_GR_HOOK);
});
register_deactivation_hook(__FILE__, static function (): void {
    wp_clear_scheduled_hook(BBT_GR_HOOK);
});
