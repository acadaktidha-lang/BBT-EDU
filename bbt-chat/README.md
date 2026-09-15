# BBT Chat

A retrieval-based chat assistant for bbt.edu.pk. It answers visitor questions
using the text of this site's own pages, and says so plainly when the answer is
not on the site.

Nothing here modifies existing site content. The plugin creates no database
tables, registers no rewrite rules and ships with the widget switched off.

## How it works

```
WordPress pages
      │  read via get_posts() + the_content
      ▼
  Text::fromHtml        strip markup, keep headings
      ▼
  Boilerplate           drop the nav/footer repeated on all 49 pages,
                        keeping one copy so the phone number survives
      ▼
  Chunker               ~220-word passages, cut on heading boundaries
      ▼
  Index (BM25)          stored as one JSON option, autoload off
      ▼
  Retriever             synonym expansion → rank pages → best passages
      ▼
  Answerer              cached system prompt (rules + facts + site map)
                        + the question and its passages
      ▼
  Provider              Mock (free) or Claude (Messages API)
```

Retrieval is keyword-based on purpose: no second vendor, no embedding key to
keep alive, pure PHP that runs on the existing Hostinger plan, and every result
is explainable. `tools/search.php` shows exactly why a passage was chosen.

## Local development

No WordPress and no API key required.

```bash
# 1. Build the index from the live site (read-only; uses the public REST API)
php bbt-chat/tools/build-index.php

# 2. Check retrieval quality — 32 cases, no network, no cost
php bbt-chat/tools/eval.php

# 3. Try it in a browser
php -S localhost:8787 bbt-chat/tools/serve.php
#    then open http://localhost:8787
```

Useful while tuning:

```bash
php bbt-chat/tools/search.php "how long is the cybersecurity course"  # what was retrieved, and why
php bbt-chat/tools/ask.php "do you teach kids?" --prompt              # the exact prompt that would be sent
```

On Windows, PHP may have no CA bundle. If `build-index.php` reports an SSL
error, point it at Git's:

```bash
export BBT_CA_BUNDLE="C:\Program Files\Git\mingw64\etc\pki\ca-trust\extracted\pem\tls-ca-bundle.pem"
```

### Using the real model locally

```bash
export ANTHROPIC_API_KEY=sk-ant-...
php bbt-chat/tools/ask.php "what are your opening hours"
```

Every call with a key set costs money. Without one, everything falls back to the
mock provider, which quotes the retrieved passages instead of writing prose — if
the mock shows the right passages, retrieval is working and only the wording is
left to the model.

## Retrieval test suite

`tests/retrieval-cases.json` holds questions and the pages that should reach the
model. Run `tools/eval.php` after any change to chunking, stemming, synonyms or
ranking. When the bot gets something wrong in the wild, add the question as a
case first, then fix it — that way it cannot regress quietly.

## Installing on WordPress

1. Zip the `bbt-chat/` directory and install it under Plugins → Add New → Upload.
2. Activate. Nothing changes on the site yet — the widget is off by default.
3. Settings → BBT Chat → **Rebuild index now**.
4. Leave the provider on **Mock** and use **Try a question** to confirm the right
   pages come back.
5. Paste the Anthropic API key, switch the provider to **Claude**, and try again.
6. Only then tick **Show the widget**.

To roll back at any point, untick **Show the widget**, or deactivate the plugin.
Both are instant and leave the site exactly as it was.

## Cost control

- Per-visitor hourly cap and a site-wide daily cap, both in Settings. The daily
  cap is a hard ceiling on what a bad day can cost.
- The instructions, key facts and site map are sent as a cached prompt prefix, so
  repeat questions are billed at the cache-read rate rather than full price.
- `max_tokens` is 1024: answers are meant to be a few sentences in a chat bubble.

## Key facts

`data/facts.md` is sent with every question, so the assistant can answer contact
details, opening hours and the fee policy even when page search finds nothing.
It is editable in the admin screen. Keep it short, and phrase entries the way
visitors ask rather than the way the site writes.

The fee rules in there are deliberate: BBT publishes no fees, and the PKR figures
on course pages are **starting salaries, not prices**. The system prompt forbids
quoting them as fees.
