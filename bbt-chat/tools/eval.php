<?php
declare(strict_types=1);

/**
 * CLI: score the retriever against tests/retrieval-cases.json.
 *
 *   php bbt-chat/tools/eval.php [-v]
 *
 * A case passes when at least one expected page appears among the pages the
 * retriever puts in front of the model. This costs nothing to run - no API key,
 * no network - so run it after every change to chunking, stemming, synonyms or
 * ranking, and before deploying a rebuilt index.
 */

require __DIR__ . '/../bootstrap.php';

use BBTChat\Index;
use BBTChat\Retriever;

$verbose = in_array('-v', array_slice($argv, 1), true);

$index     = Index::fromJson((string) file_get_contents(__DIR__ . '/../data/index.json'));
$retriever = new Retriever($index);
$suite     = json_decode((string) file_get_contents(__DIR__ . '/../tests/retrieval-cases.json'), true);

$passed  = 0;
$failed  = [];

foreach ((array) ($suite['cases'] ?? []) as $case) {
    $question = (string) $case['q'];
    $expected = (array) $case['expect'];

    $paths = [];
    foreach ($retriever->retrieve($question) as $rank => $hit) {
        $path = parse_url($hit['chunk']->url, PHP_URL_PATH) ?: '/';
        $paths[$path] ??= $rank + 1;
    }

    $hitPath = null;
    foreach ($expected as $path) {
        if (isset($paths[$path])) {
            $hitPath = $path;
            break;
        }
    }

    if ($hitPath !== null) {
        $passed++;
        if ($verbose) {
            printf("  ok   %-52s -> %s (rank %d)\n", mb_substr($question, 0, 50), $hitPath, $paths[$hitPath]);
        }
        continue;
    }

    $failed[] = ['q' => $question, 'expected' => $expected, 'got' => array_keys($paths)];
}

$total = $passed + count($failed);

if ($failed !== []) {
    echo "\nFAILURES\n";
    foreach ($failed as $failure) {
        printf("  %s\n    expected any of: %s\n    retrieved:       %s\n",
            $failure['q'],
            implode(', ', $failure['expected']),
            implode(', ', $failure['got']) ?: '(nothing)'
        );
    }
}

printf("\n%d/%d retrieval cases passed (%.0f%%)\n", $passed, $total, $total > 0 ? $passed / $total * 100 : 0);

exit($failed === [] ? 0 : 1);
