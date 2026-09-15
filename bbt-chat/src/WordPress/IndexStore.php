<?php
declare(strict_types=1);

namespace BBTChat\WordPress;

use BBTChat\Boilerplate;
use BBTChat\Chunker;
use BBTChat\Index;
use BBTChat\Text;

/**
 * Builds the retrieval index from this site's own published pages and caches it.
 *
 * Reading the pages straight out of WordPress is what keeps the bot honest: edit
 * a page, and the answer changes with it. There is no export step to forget and
 * no copy of the content to drift out of date.
 */
final class IndexStore
{
    public const OPTION       = 'bbt_chat_index';
    public const BUILT_OPTION = 'bbt_chat_index_built';
    public const REBUILD_HOOK = 'bbt_chat_rebuild_index';

    private ?Index $loaded = null;

    public function index(): ?Index
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $json = get_option(self::OPTION, '');
        if (!is_string($json) || $json === '') {
            return null;
        }

        try {
            return $this->loaded = Index::fromJson($json);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function builtAt(): ?string
    {
        $built = get_option(self::BUILT_OPTION, '');

        return is_string($built) && $built !== '' ? $built : null;
    }

    /**
     * @return array{pages:int,passages:int} counts for the admin screen
     */
    public function rebuild(): array
    {
        $posts = get_posts([
            'post_type'        => ['page'],
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'suppress_filters' => false,
        ]);

        $meta  = [];
        $texts = [];
        foreach ($posts as $post) {
            if ($this->isExcluded($post)) {
                continue;
            }
            $html = (string) apply_filters('the_content', $post->post_content);
            if (trim(wp_strip_all_tags($html)) === '') {
                continue;
            }
            $id         = 'p' . $post->ID;
            $meta[$id]  = ['url' => (string) get_permalink($post), 'title' => get_the_title($post)];
            $texts[$id] = Text::fromHtml($html);
        }

        $sharedBlock = Boilerplate::sharedBlock($texts);
        $texts       = Boilerplate::strip($texts);

        $chunker = new Chunker();
        $chunks  = [];
        foreach ($texts as $id => $text) {
            $chunks = array_merge(
                $chunks,
                $chunker->chunkText($id, $meta[$id]['url'], $meta[$id]['title'], $text)
            );
        }

        if (trim($sharedBlock) !== '') {
            $chunks = array_merge($chunks, (new Chunker(targetWords: 55, maxWords: 90, minWords: 4))->chunkText(
                'site',
                $this->contactUrl($meta),
                'Contact details — phone number, WhatsApp, email address, campus address, opening hours, site menu',
                $sharedBlock
            ));
        }

        $index = Index::build($chunks);

        // autoload 'no': the index is a few hundred KB and is only needed on the
        // one REST route, never on a normal page load.
        update_option(self::OPTION, $index->toJson(), false);
        update_option(self::BUILT_OPTION, current_time('mysql'), false);

        $this->loaded = $index;

        return ['pages' => count($index->pages()), 'passages' => count($index->chunks())];
    }

    /**
     * Queues a rebuild rather than running one inline, so saving a page in the
     * editor never waits on indexing 49 pages.
     */
    public function scheduleRebuild(): void
    {
        if (!wp_next_scheduled(self::REBUILD_HOOK)) {
            wp_schedule_single_event(time() + 120, self::REBUILD_HOOK);
        }
    }

    private function isExcluded(\WP_Post $post): bool
    {
        if (post_password_required($post) || $post->post_password !== '') {
            return true;
        }
        if ((int) get_option('page_for_privacy_policy') === $post->ID) {
            return true;
        }

        /**
         * Filter: exclude a page from the chat index.
         *
         * @param bool     $excluded
         * @param \WP_Post $post
         */
        return (bool) apply_filters('bbt_chat_exclude_page', false, $post);
    }

    /** @param array<string,array{url:string,title:string}> $meta */
    private function contactUrl(array $meta): string
    {
        foreach ($meta as $page) {
            if (str_contains($page['url'], 'contact')) {
                return $page['url'];
            }
        }

        return home_url('/');
    }
}
