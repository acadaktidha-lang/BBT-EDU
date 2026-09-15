<?php
declare(strict_types=1);

/**
 * Local dev server: the real widget, the real retrieval core, no WordPress.
 *
 *   php -S localhost:8787 bbt-chat/tools/serve.php
 *   open http://localhost:8787
 *
 * Lets the whole thing be exercised in a browser before anything is installed
 * on the live site. Uses the mock provider unless ANTHROPIC_API_KEY is set.
 */

require __DIR__ . '/../bootstrap.php';

use BBTChat\Answerer;
use BBTChat\Index;
use BBTChat\Provider\ClaudeProvider;
use BBTChat\Provider\MockProvider;
use BBTChat\Provider\ProviderException;
use BBTChat\Retriever;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/assets/widget.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    readfile(__DIR__ . '/../assets/widget.js');
    return true;
}

if ($path === '/ask' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $payload  = json_decode((string) file_get_contents('php://input'), true);
    $question = trim((string) ($payload['question'] ?? ''));
    $history  = (array) ($payload['history'] ?? []);

    if ($question === '') {
        http_response_code(400);
        echo json_encode(['message' => 'Please type a question.']);
        return true;
    }

    try {
        $index     = Index::fromJson((string) file_get_contents(__DIR__ . '/../data/index.json'));
        $retriever = new Retriever($index);
        $facts     = (string) @file_get_contents(__DIR__ . '/../data/facts.md');

        $key      = (string) (getenv('ANTHROPIC_API_KEY') ?: '');
        $provider = $key === ''
            ? new MockProvider()
            : new ClaudeProvider($key, (string) (getenv('BBT_MODEL') ?: 'claude-opus-5'));

        $result = (new Answerer($retriever, $provider, $facts))->ask($question, $history);
        $answer = $result['answer'];

        if ($answer->wasRefused()) {
            echo json_encode([
                'answer'  => 'I can\'t help with that one. For anything about courses or admission, '
                    . 'message us on WhatsApp at 0321-0908881.',
                'sources' => [],
            ]);
            return true;
        }

        echo json_encode([
            'answer'   => $answer->text,
            'sources'  => $result['sources'],
            'provider' => $provider->name(),
            'usage'    => $answer->usage,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (ProviderException $e) {
        http_response_code(502);
        echo json_encode(['message' => 'The assistant is unavailable: ' . $e->getMessage()]);
    }

    return true;
}

if ($path !== '/') {
    http_response_code(404);
    echo 'Not found';
    return true;
}

$indexPath  = __DIR__ . '/../data/index.json';
$indexBuilt = is_file($indexPath)
    ? date('Y-m-d H:i', (int) filemtime($indexPath)) . ' · ' . number_format(filesize($indexPath) / 1024, 0) . ' KB'
    : 'NOT BUILT — run tools/build-index.php first';
$providerName = getenv('ANTHROPIC_API_KEY') ? 'Claude (live API, real cost)' : 'Mock (free, no API key)';

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BBT chat — local test</title>
<style>
  body { margin: 0; background: #F4F6F9; color: #0D1B2A;
         font: 15px/1.6 "IBM Plex Sans", -apple-system, Segoe UI, sans-serif; }
  main { max-width: 720px; margin: 0 auto; padding: 56px 20px 160px; }
  h1 { font-size: 28px; margin: 0 0 6px; }
  .mono { font: 11px/1.6 "IBM Plex Mono", ui-monospace, monospace;
          letter-spacing: .14em; text-transform: uppercase; color: #8A99A8; }
  dl { display: grid; grid-template-columns: max-content 1fr; gap: 8px 18px;
       background: #fff; border: 1px solid #DCE3EC; border-radius: 12px; padding: 18px; margin: 24px 0; }
  dt { color: #475569; font-weight: 600; }
  dd { margin: 0; }
  ol { background: #fff; border: 1px solid #DCE3EC; border-radius: 12px; padding: 18px 18px 18px 38px; }
  code { background: #EEF2F7; padding: 1px 5px; border-radius: 4px;
         font-family: "IBM Plex Mono", ui-monospace, monospace; font-size: 13px; }
  .fake-whatsapp { position: fixed; right: 24px; bottom: 24px; width: 56px; height: 56px;
    border-radius: 50%; background: #16794D; color: #fff; display: grid; place-items: center;
    font-size: 11px; box-shadow: 0 18px 48px rgba(13,27,42,.28); }
</style>
</head>
<body>
<main>
  <p class="mono">local test harness</p>
  <h1>BBT chat widget</h1>
  <p>This page is not the BBT site. It exists to exercise the widget, the retrieval
     index and the answer endpoint locally, before anything is installed on WordPress.</p>

  <dl>
    <dt>Provider</dt><dd><?= htmlspecialchars($providerName, ENT_QUOTES) ?></dd>
    <dt>Index</dt><dd><?= htmlspecialchars($indexBuilt, ENT_QUOTES) ?></dd>
    <dt>Endpoint</dt><dd><code>POST /ask</code></dd>
  </dl>

  <ol>
    <li>Click the chat bubble, bottom right.</li>
    <li>Try a starter question, then something awkward: fees, a course that does not exist, a question in Urdu.</li>
    <li>Check the page links under each answer point somewhere sensible.</li>
  </ol>

  <p>The green circle imitates the WhatsApp button already on the live site, so you can
     confirm the chat bubble sits clear of it rather than on top of it.</p>
</main>

<div class="fake-whatsapp" aria-hidden="true">WA</div>

<script>
  window.BBT_CHAT_CONFIG = {
    endpoint: '/ask',
    title: 'Ask BBT',
    greeting: 'Hello. I can answer questions about BBT courses, tracks, timings and admission. What would you like to know?'
  };
</script>
<script src="/assets/widget.js"></script>
</body>
</html>
<?php
return true;
