<?php
declare(strict_types=1);

/**
 * CLI: ask the bot a question end to end.
 *
 *   php bbt-chat/tools/ask.php "do you teach kids?"
 *
 * Uses the mock provider unless ANTHROPIC_API_KEY is set, so the whole pipeline
 * can be exercised without credentials. Add --prompt to dump the exact prompt
 * that would be sent.
 */

require __DIR__ . '/../bootstrap.php';

use BBTChat\Answerer;
use BBTChat\Index;
use BBTChat\Provider\ClaudeProvider;
use BBTChat\Provider\MockProvider;
use BBTChat\Retriever;

$args     = array_slice($argv, 1);
$showPrompt = in_array('--prompt', $args, true);
$question = '';
foreach ($args as $arg) {
    if (!str_starts_with($arg, '--')) {
        $question = $arg;
        break;
    }
}

if (trim($question) === '') {
    fwrite(STDERR, "usage: php tools/ask.php \"your question\" [--prompt]\n");
    exit(1);
}

$index     = Index::fromJson((string) file_get_contents(__DIR__ . '/../data/index.json'));
$retriever = new Retriever($index);
$facts     = (string) @file_get_contents(__DIR__ . '/../data/facts.md');

$key      = (string) (getenv('ANTHROPIC_API_KEY') ?: '');
$model    = (string) (getenv('BBT_MODEL') ?: 'claude-opus-5');
$provider = $key === '' ? new MockProvider() : new ClaudeProvider($key, $model);

$answerer = new Answerer($retriever, $provider, $facts);

if ($showPrompt) {
    $system = $answerer->systemPrompt();
    echo "===== SYSTEM (cached prefix, ~", BBTChat\Text::estimateTokens($system), " tokens) =====\n";
    echo $system, "\n\n";
}

$result = $answerer->ask($question);
$answer = $result['answer'];

echo "===== PROVIDER: ", $provider->name(), " =====\n";
if ($answer->wasRefused()) {
    echo "Request was refused (category: {$answer->refusalCategory}).\n";
} else {
    echo $answer->text, "\n";
}

echo "\n----- pages used -----\n";
foreach ($result['sources'] as $source) {
    echo '  ', $source['url'], "\n";
}

if ($answer->usage !== []) {
    echo "\n----- usage -----\n";
    foreach ($answer->usage as $metric => $count) {
        printf("  %-28s %s\n", $metric, number_format($count));
    }
}
