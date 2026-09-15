<?php
declare(strict_types=1);

/**
 * CLI: build the retrieval index from the live site and write data/index.json.
 *
 *   php bbt-chat/tools/build-index.php [--site=https://bbt.edu.pk] [--out=path]
 */

require __DIR__ . '/../bootstrap.php';

use BBTChat\Boilerplate;
use BBTChat\Chunker;
use BBTChat\Index;
use BBTChat\SiteSource;
use BBTChat\Text;

$options = getopt('', ['site::', 'out::']);
$site    = $options['site'] ?? 'https://bbt.edu.pk';
$out     = $options['out']  ?? __DIR__ . '/../data/index.json';

fwrite(STDERR, "Fetching pages from $site ...\n");
$pages = SiteSource::fromRestApi($site);
fwrite(STDERR, 'Fetched ' . count($pages) . " pages\n");

// Extract every page first: chrome can only be identified by comparing pages.
$texts = [];
foreach ($pages as $page) {
    $texts[$page['id']] = Text::fromHtml($page['html']);
}
$rawWords    = array_sum(array_map('str_word_count', $texts));
$sharedBlock = Boilerplate::sharedBlock($texts);
$texts       = Boilerplate::strip($texts);
$netWords    = array_sum(array_map('str_word_count', $texts));

fwrite(STDERR, sprintf(
    "Removed repeated nav/footer text: %s of %s words (%.0f%%)\n\n",
    number_format($rawWords - $netWords),
    number_format($rawWords),
    $rawWords > 0 ? ($rawWords - $netWords) / $rawWords * 100 : 0
));

$chunker = new Chunker();
$chunks  = [];
foreach ($pages as $page) {
    $pageChunks = $chunker->chunkText($page['id'], $page['url'], $page['title'], $texts[$page['id']]);
    $chunks     = array_merge($chunks, $pageChunks);
    fwrite(STDERR, sprintf("  %-46s %3d chunks\n", parse_url($page['url'], PHP_URL_PATH) ?: '/', count($pageChunks)));
}

// The chrome, indexed exactly once, pointed at the contact page.
if (trim($sharedBlock) !== '') {
    $contactUrl = rtrim($site, '/') . '/contact-bbt/';
    foreach ($pages as $page) {
        if (str_contains($page['url'], 'contact')) {
            $contactUrl = $page['url'];
            break;
        }
    }
    // Finer chunks here than for prose: the footer's contact block is ten words
    // long and must not be buried inside a passage of menu links.
    $sharedChunks = (new Chunker(targetWords: 55, maxWords: 90, minWords: 4))->chunkText(
        'site',
        $contactUrl,
        // Title doubles as the vocabulary hook: the footer text itself never says
        // "phone", "number" or "email address", which is exactly how visitors ask.
        'Contact details — phone number, WhatsApp, email address, campus address, opening hours, site menu',
        $sharedBlock
    );
    $chunks = array_merge($chunks, $sharedChunks);
    fwrite(STDERR, sprintf("  %-46s %3d chunks (site-wide)\n", '[menu + footer]', count($sharedChunks)));
}

$index = Index::build($chunks);
file_put_contents($out, $index->toJson());

fwrite(STDERR, sprintf(
    "\nIndexed %d passages from %d pages\nWrote %s (%s KB)\n",
    count($index->chunks()),
    count($index->pages()),
    $out,
    number_format(filesize($out) / 1024, 1)
));
