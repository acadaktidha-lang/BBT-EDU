# BBT Google Reviews

Keeps the home page review slider filled with the latest Google reviews.

Trustindex ("Widgets for Google Reviews") pulls new reviews from Google at
least once a day. Every 6 hours this plugin reads Trustindex's review list,
applies the rules below and stores the result. When the home page renders,
the cards between `<!-- BBT-REVIEWS:START -->` and `<!-- BBT-REVIEWS:END -->`
are replaced with those reviews, in the site's own card design.

## Rules

Set in `bbt_gr_rules()`, overridable with the `bbt_google_reviews_rules` filter:

| rule        | default        | meaning |
|-------------|----------------|---------|
| `min_stars` | `4.0`          | lowest star rating shown |
| `photo`     | `only`         | `only` = reviewers with a real profile photo; `prefer` = photo reviews first; `any` |
| `exclude`   | `['Mr Chicken']` | reviewer names never shown |
| `max`       | `8`            | most cards shown, newest first |
| `min_shown` | `3`            | fewer matches than this and the static cards in the page stay |

A real photo is told apart from Google's default letter avatar by file size
(letter avatars are under 3 KB at 160px).

## Safety

- If the plugin or Trustindex is deactivated, or too few reviews match, the
  static cards written in the page show as before.
- A failed refresh keeps the last good set.
- No database tables; it stores two options (`bbt_google_reviews`,
  `bbt_google_reviews_photos`).

## Admin endpoints

- `GET  /wp-json/bbt-google-reviews/v1/status` - last refresh and what is shown
- `POST /wp-json/bbt-google-reviews/v1/refresh` - refresh now

Both need an administrator (an application password works).

## Tests

    php tests/run.php

Runs the parser, selector and renderer against two saved Trustindex layouts.

## Install

Upload `bbt-google-reviews.zip` under Plugins > Add New > Upload Plugin and
activate it.
