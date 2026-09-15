<?php
/**
 * Plugin Name:       BBT Chat
 * Description:       Retrieval-based chat assistant that answers visitor questions from the content of this site.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Big Binary Trainings
 * License:           GPL-2.0-or-later
 * Text Domain:       bbt-chat
 *
 * Designed to be additive: it creates no database tables, modifies no existing
 * content, registers no rewrite rules and touches no other plugin. Deactivating
 * it returns the site to exactly its current behaviour.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('BBT_CHAT_VERSION', '0.1.0');
define('BBT_CHAT_FILE', __FILE__);
define('BBT_CHAT_DIR', plugin_dir_path(__FILE__));
define('BBT_CHAT_URL', plugin_dir_url(__FILE__));

require_once BBT_CHAT_DIR . 'bootstrap.php';

// The retrieval core is plain PHP with no WordPress calls, so it stays usable
// from the CLI tools; only the classes under WordPress/ know about WordPress.
add_action('plugins_loaded', static function (): void {
    (new BBTChat\WordPress\Plugin())->boot();
});

register_deactivation_hook(__FILE__, static function (): void {
    wp_clear_scheduled_hook(BBTChat\WordPress\IndexStore::REBUILD_HOOK);
});
