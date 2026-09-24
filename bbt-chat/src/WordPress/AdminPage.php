<?php
declare(strict_types=1);

namespace BBTChat\WordPress;

use BBTChat\Answerer;
use BBTChat\Provider\ClaudeProvider;
use BBTChat\Provider\MockProvider;
use BBTChat\Provider\ProviderException;
use BBTChat\Retriever;

/** Settings screen: switch, credentials, index rebuild, and a question box to try it. */
final class AdminPage
{
    private const SLUG = 'bbt-chat';

    public function __construct(
        private readonly Settings $settings,
        private readonly IndexStore $store,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_options_page('BBT Chat', 'BBT Chat', 'manage_options', self::SLUG, [$this, 'render']);
        });

        add_action('admin_init', function (): void {
            register_setting(self::SLUG, Settings::OPTION, [
                'sanitize_callback' => [$this->settings, 'sanitize'],
            ]);
        });

        add_action('admin_post_bbt_chat_rebuild', [$this, 'handleRebuild']);
    }

    public function handleRebuild(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('bbt_chat_rebuild')) {
            wp_die('Not allowed.');
        }

        $counts = $this->store->rebuild();

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'rebuilt' => $counts['passages'], 'pages' => $counts['pages']],
            admin_url('options-general.php')
        ));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $values  = $this->settings->all();
        $builtAt = $this->store->builtAt();
        $index   = $this->store->index();
        ?>
        <div class="wrap">
            <h1>BBT Chat</h1>

            <?php if (isset($_GET['rebuilt'])) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    Index rebuilt: <?= (int) $_GET['rebuilt'] ?> passages from <?= (int) ($_GET['pages'] ?? 0) ?> pages.
                </p></div>
            <?php endif; ?>

            <?php if ($values['enabled'] && $index === null) : ?>
                <div class="notice notice-warning"><p>
                    The assistant is switched on but the index has never been built, so the widget stays hidden.
                    Use <strong>Rebuild index now</strong> below.
                </p></div>
            <?php endif; ?>

            <?php if ($values['provider'] === 'claude' && trim((string) $values['api_key']) === '') : ?>
                <div class="notice notice-error"><p>
                    Provider is set to Claude but no API key is saved. Add a key, or switch the provider back to
                    Mock while you are testing.
                </p></div>
            <?php endif; ?>

            <h2>Content index</h2>
            <table class="widefat striped" style="max-width:640px">
                <tbody>
                    <tr><th style="width:180px">Last built</th><td><?= esc_html($builtAt ?? 'never') ?></td></tr>
                    <tr><th>Pages indexed</th><td><?= $index ? count($index->pages()) : 0 ?></td></tr>
                    <tr><th>Passages</th><td><?= $index ? count($index->chunks()) : 0 ?></td></tr>
                </tbody>
            </table>
            <p>The index rebuilds automatically a couple of minutes after any page is saved.</p>
            <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="margin-bottom:28px">
                <input type="hidden" name="action" value="bbt_chat_rebuild">
                <?php wp_nonce_field('bbt_chat_rebuild'); ?>
                <?php submit_button('Rebuild index now', 'secondary', 'submit', false); ?>
            </form>

            <form method="post" action="options.php">
                <?php settings_fields(self::SLUG); ?>
                <?php $name = Settings::OPTION; ?>

                <h2>Behaviour</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Show the widget</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?= $name ?>[enabled]" value="1"
                                    <?php checked($values['enabled']); ?>>
                                Display the chat bubble to visitors
                            </label>
                            <p class="description">Off by default. Nothing appears on the site until this is ticked.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bbt-provider">Answer provider</label></th>
                        <td>
                            <select id="bbt-provider" name="<?= $name ?>[provider]">
                                <option value="mock" <?php selected($values['provider'], 'mock'); ?>>
                                    Mock — quotes the matching page text, costs nothing, no API key
                                </option>
                                <option value="claude" <?php selected($values['provider'], 'claude'); ?>>
                                    Claude — real answers, billed to your Anthropic account
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bbt-key">Anthropic API key</label></th>
                        <td>
                            <input id="bbt-key" type="password" class="regular-text" autocomplete="off"
                                   name="<?= $name ?>[api_key]"
                                   placeholder="<?= trim((string) $values['api_key']) !== '' ? 'saved — leave blank to keep' : 'sk-ant-…' ?>">
                            <p class="description">Stored in the database. Leave blank to keep the existing key.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bbt-model">Model</label></th>
                        <td>
                            <select id="bbt-model" name="<?= $name ?>[model]">
                                <?php foreach ([
                                    'claude-opus-5'   => 'Claude Opus 5 — best quality',
                                    'claude-sonnet-5' => 'Claude Sonnet 5 — cheaper',
                                    'claude-haiku-4-5' => 'Claude Haiku 4.5 — cheapest and fastest',
                                ] as $id => $label) : ?>
                                    <option value="<?= esc_attr($id) ?>" <?php selected($values['model'], $id); ?>>
                                        <?= esc_html($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>Wording</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bbt-title">Panel title</label></th>
                        <td><input id="bbt-title" type="text" class="regular-text"
                                   name="<?= $name ?>[title]" value="<?= esc_attr((string) $values['title']) ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bbt-greeting">Greeting</label></th>
                        <td><textarea id="bbt-greeting" class="large-text" rows="2"
                                      name="<?= $name ?>[greeting]"><?= esc_textarea((string) $values['greeting']) ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bbt-starters">Starter questions</label></th>
                        <td>
                            <textarea id="bbt-starters" class="large-text code" rows="4"
                                      name="<?= $name ?>[starters]"><?= esc_textarea((string) $values['starters']) ?></textarea>
                            <p class="description">One per line.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bbt-facts">Key facts</label></th>
                        <td>
                            <textarea id="bbt-facts" class="large-text code" rows="14"
                                      name="<?= $name ?>[facts]"><?= esc_textarea($this->settings->facts()) ?></textarea>
                            <p class="description">
                                Sent with every question, so these are answerable even when page search finds nothing.
                                Phrase them the way visitors ask. Keep it short.
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>Spending limits</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bbt-ip-limit">Questions per visitor, per hour</label></th>
                        <td><input id="bbt-ip-limit" type="number" min="1" max="200" class="small-text"
                                   name="<?= $name ?>[questions_per_ip]" value="<?= (int) $values['questions_per_ip'] ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bbt-day-limit">Questions site-wide, per day</label></th>
                        <td>
                            <input id="bbt-day-limit" type="number" min="10" max="20000" class="small-text"
                                   name="<?= $name ?>[questions_per_day]" value="<?= (int) $values['questions_per_day'] ?>">
                            <p class="description">
                                A hard ceiling on what a bad day can cost. Past it, visitors are pointed at WhatsApp.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2>Try a question</h2>
            <?php $this->renderTester(); ?>
        </div>
        <?php
    }

    /** Asks a question using the saved settings, so behaviour can be checked before going live. */
    private function renderTester(): void
    {
        $question = isset($_POST['bbt_chat_test_question'])
            ? sanitize_textarea_field((string) $_POST['bbt_chat_test_question'])
            : '';
        $valid = $question !== ''
            && isset($_POST['bbt_chat_test_nonce'])
            && wp_verify_nonce(sanitize_text_field((string) $_POST['bbt_chat_test_nonce']), 'bbt_chat_test');
        ?>
        <form method="post">
            <?php wp_nonce_field('bbt_chat_test', 'bbt_chat_test_nonce'); ?>
            <input type="text" class="large-text" name="bbt_chat_test_question"
                   value="<?= esc_attr($question) ?>" placeholder="e.g. do you have classes for kids?">
            <?php submit_button('Ask', 'secondary'); ?>
        </form>
        <?php
        if (!$valid) {
            return;
        }

        $index = $this->store->index();
        if ($index === null) {
            echo '<div class="notice notice-error"><p>Build the index first.</p></div>';
            return;
        }

        $values   = $this->settings->all();
        $provider = $values['provider'] === 'claude'
            ? new ClaudeProvider(
                (string) $values['api_key'],
                (string) $values['model'],
                (string) $values['effort'],
                (int) $values['max_tokens']
            )
            : new MockProvider();

        try {
            $result = (new Answerer(new Retriever($index), $provider, $this->settings->facts()))->ask($question);
        } catch (ProviderException $e) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($e->getMessage()));
            return;
        }

        echo '<div class="notice notice-info"><p><strong>Answer</strong></p><p style="white-space:pre-wrap">'
            . esc_html($result['answer']->text) . '</p><p><strong>Pages used</strong></p><ul>';
        foreach ($result['sources'] as $source) {
            printf('<li><a href="%1$s" target="_blank">%1$s</a></li>', esc_url($source['url']));
        }
        echo '</ul></div>';
    }
}
