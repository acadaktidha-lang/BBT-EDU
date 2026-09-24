<?php
declare(strict_types=1);

namespace BBTChat;

/**
 * Okapi BM25 over the site's passages.
 *
 * Small enough to hold in memory (50 pages produces a few hundred passages),
 * so the whole index serialises to one JSON blob and needs no search service.
 */
final class Index
{
    private const K1 = 1.2;
    private const B  = 0.75;

    /**
     * Boost applied to a passage whose page title or headings match a query term.
     * Kept modest: this site's SEO titles list every subject it teaches, so a
     * large boost makes every page look relevant to every subject.
     */
    private const TITLE_BOOST = 1.35;

    /**
     * @param list<Chunk>                 $chunks
     * @param list<array<string,int>>     $termFrequencies parallel to $chunks
     * @param array<string,int>           $documentFrequencies
     * @param list<int>                   $lengths         parallel to $chunks
     * @param list<array<string,true>>    $titleTerms      parallel to $chunks
     */
    private function __construct(
        private readonly array $chunks,
        private readonly array $termFrequencies,
        private readonly array $documentFrequencies,
        private readonly array $lengths,
        private readonly array $titleTerms,
        private readonly float $averageLength,
        public readonly string $builtAt,
    ) {
    }

    /**
     * @param list<Chunk> $chunks
     */
    public static function build(array $chunks): self
    {
        $chunks = array_values(self::dropBoilerplate($chunks));

        $termFrequencies = [];
        $documentFreqs   = [];
        $lengths         = [];
        $titleTerms      = [];

        foreach ($chunks as $chunk) {
            $tokens = Text::tokenize($chunk->searchableText());
            $counts = array_count_values($tokens);

            $termFrequencies[] = $counts;
            $lengths[]         = count($tokens);
            foreach (array_keys($counts) as $term) {
                $documentFreqs[$term] = ($documentFreqs[$term] ?? 0) + 1;
            }

            $title = Text::tokenize($chunk->pageTitle . ' ' . implode(' ', $chunk->headings));
            $titleTerms[] = array_fill_keys($title, true);
        }

        $average = $lengths === [] ? 1.0 : array_sum($lengths) / count($lengths);

        return new self(
            $chunks,
            $termFrequencies,
            $documentFreqs,
            $lengths,
            $titleTerms,
            max($average, 1.0),
            gmdate('c'),
        );
    }

    /**
     * Nav and footer text is repeated verbatim on every page. Left in, it
     * dominates the index and every query retrieves the menu.
     *
     * @param  list<Chunk> $chunks
     * @return list<Chunk>
     */
    private static function dropBoilerplate(array $chunks): array
    {
        $pages = [];
        $seen  = [];
        foreach ($chunks as $chunk) {
            $pages[$chunk->url] = true;
            $fingerprint = md5(preg_replace('/\s+/u', ' ', mb_strtolower($chunk->text)) ?? $chunk->text);
            $seen[$fingerprint][$chunk->url] = true;
        }

        $pageCount = max(count($pages), 1);
        $threshold = max(3, (int) ceil($pageCount * 0.4));

        return array_values(array_filter($chunks, static function (Chunk $chunk) use ($seen, $threshold): bool {
            $fingerprint = md5(preg_replace('/\s+/u', ' ', mb_strtolower($chunk->text)) ?? $chunk->text);

            return count($seen[$fingerprint]) < $threshold;
        }));
    }

    /**
     * @param  array<string,float> $weights per-term multipliers (stemmed term => weight);
     *                                      terms not listed count as 1.0. Lets the caller
     *                                      add synonyms without letting them outvote the
     *                                      words the visitor actually typed.
     * @return list<array{chunk:Chunk,score:float,terms:list<string>}> highest scoring first
     */
    public function search(string $query, int $limit = 6, array $weights = []): array
    {
        $terms = Text::tokenize($query);
        if ($terms === [] || $this->chunks === []) {
            return [];
        }

        $total   = count($this->chunks);
        $scores  = [];
        $matched = [];

        foreach (array_unique($terms) as $term) {
            $df = $this->documentFrequencies[$term] ?? 0;
            if ($df === 0) {
                continue;
            }
            $idf        = log(1.0 + (($total - $df + 0.5) / ($df + 0.5)));
            $termWeight = $weights[$term] ?? 1.0;

            foreach ($this->termFrequencies as $i => $counts) {
                $tf = $counts[$term] ?? 0;
                if ($tf === 0) {
                    continue;
                }
                $norm = $tf * (self::K1 + 1)
                    / ($tf + self::K1 * (1 - self::B + self::B * ($this->lengths[$i] / $this->averageLength)));
                $boost = isset($this->titleTerms[$i][$term]) ? self::TITLE_BOOST : 1.0;

                $scores[$i]    = ($scores[$i] ?? 0.0) + $idf * $norm * $boost * $termWeight;
                $matched[$i][] = $term;
            }
        }

        if ($scores === []) {
            return [];
        }

        arsort($scores);

        $results = [];
        foreach (array_slice($scores, 0, $limit, true) as $i => $score) {
            $results[] = [
                'chunk' => $this->chunks[$i],
                'score' => round($score, 4),
                'terms' => array_values(array_unique($matched[$i])),
            ];
        }

        return $results;
    }

    /** True when the term appears anywhere in the corpus. */
    public function knowsTerm(string $term): bool
    {
        return ($this->documentFrequencies[$term] ?? 0) > 0;
    }

    /** @return list<Chunk> */
    public function chunks(): array
    {
        return $this->chunks;
    }

    /** @return list<array{url:string,title:string}> one entry per indexed page, in index order */
    public function pages(): array
    {
        $pages = [];
        foreach ($this->chunks as $chunk) {
            $pages[$chunk->url] ??= ['url' => $chunk->url, 'title' => $chunk->pageTitle];
        }

        return array_values($pages);
    }

    public function toJson(): string
    {
        return json_encode([
            'version'  => 1,
            'builtAt'  => $this->builtAt,
            'chunks'   => array_map(static fn (Chunk $c): array => $c->toArray(), $this->chunks),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Rebuilds the statistics from the stored passages. Storing only the text
     * keeps the serialised index small and means a scoring change never needs
     * a stale on-disk format migrated.
     */
    public static function fromJson(string $json): self
    {
        $data   = json_decode($json, true);
        $chunks = array_map(
            static fn (array $c): Chunk => Chunk::fromArray($c),
            (array) ($data['chunks'] ?? [])
        );

        return self::build($chunks);
    }
}
