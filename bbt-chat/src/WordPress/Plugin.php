<?php
declare(strict_types=1);

namespace BBTChat\WordPress;

/** Wires the plugin into WordPress. Every hook this plugin registers is listed here. */
final class Plugin
{
    private Settings $settings;
    private IndexStore $store;

    public function __construct()
    {
        $this->settings = new Settings();
        $this->store    = new IndexStore();
    }

    public function boot(): void
    {
        add_action('rest_api_init', function (): void {
            (new RestController($this->settings, $this->store))->register();
        });

        add_action('wp_enqueue_scripts', [$this, 'enqueueWidget']);

        // Re-index after a page changes, on a delay, so the editor stays fast.
        add_action('save_post_page', [$this, 'onPageSaved'], 10, 3);
        add_action('deleted_post', [$this, 'onPageDeleted'], 10, 2);
        add_action(IndexStore::REBUILD_HOOK, function (): void {
            $this->store->rebuild();
        });

        if (is_admin()) {
            (new AdminPage($this->settings, $this->store))->register();
        }
    }

    public function enqueueWidget(): void
    {
        if (!$this->settings->get('enabled') || $this->store->index() === null) {
            return;
        }
        if (is_admin() || is_feed() || is_embed()) {
            return;
        }

        wp_enqueue_script(
            'bbt-chat-widget',
            BBT_CHAT_URL . 'assets/widget.js',
            [],
            BBT_CHAT_VERSION,
            true
        );

        wp_add_inline_script(
            'bbt-chat-widget',
            'window.BBT_CHAT_CONFIG = ' . wp_json_encode([
                'endpoint' => esc_url_raw(rest_url('bbt-chat/v1/ask')),
                'nonce'    => wp_create_nonce('wp_rest'),
                'title'    => (string) $this->settings->get('title'),
                'greeting' => (string) $this->settings->get('greeting'),
                'starters' => $this->settings->starters(),
            ]) . ';',
            'before'
        );
    }

    public function onPageSaved(int $postId, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if ($post->post_status !== 'publish' && !$update) {
            return;
        }
        $this->store->scheduleRebuild();
    }

    public function onPageDeleted(int $postId, ?\WP_Post $post = null): void
    {
        if ($post instanceof \WP_Post && $post->post_type === 'page') {
            $this->store->scheduleRebuild();
        }
    }
}
