<?php
declare(strict_types=1);

/**
 * CLI: inspect what the retriever returns for a question.
 *
 *   php bbt-chat/tools/search.php "how long is the cybersecurity course"
 */

require __DIR__ . '/../bootstrap.php';

use BBTChat\Index;
use BBTChat\Retriever;

$query = $argv[1] ?? '';
if (trim($query) === '') {
    fwrite(STDERR, "usage: php tools/search.php \"your question\"\n");
    exit(1);
}

$index     = Index::fromJson((string) file_get_contents(__DIR__ . '/../data/index.json'));
$retriever = new Retriever($index);
$hits      = $retriever->retrieve($query);

printf("expanded: %s\n", $retriever->expand($query));

if ($hits === []) {
    echo "No passages matched.\n";
    exit(0);
}

foreach ($hits as $rank => $hit) {
    $chunk = $hit['chunk'];
    printf("\n[%d] %-8s %s\n     %s\n", $rank + 1, $hit['score'], $chunk->url, $chunk->label());
    echo '     ' . str_replace("\n", "\n     ", mb_substr($chunk->text, 0, 260)) . "\n";
}
