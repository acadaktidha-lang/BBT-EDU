<?php
declare(strict_types=1);

namespace BBTChat;

/**
 * Turns a visitor's question into the context block the model answers from.
 *
 * Two stages. First rank pages rather than passages: a page whose passages
 * score well repeatedly is more likely to be *about* the topic than a hub page
 * that merely lists it. Then take the best passages from the best pages. This
 * stops /courses/ - whose title names every subject on the site - from
 * occupying every slot for every subject query.
 */
final class Retriever
{
    /** Passages pulled from the index before page-level grouping. */
    private const CANDIDATE_POOL = 40;

    /**
     * Site vocabulary that visitors phrase differently to the page copy.
     * Kept deliberately tight - every extra term dilutes the ranking.
     *
     * @var array<string,list<string>>
     */
    private const SYNONYMS = [
        'fee'          => ['fees', 'cost', 'price', 'charges', 'tuition', 'payment'],
        'cost'         => ['fee', 'fees', 'price', 'charges'],
        'price'        => ['fee', 'fees', 'cost'],
        'duration'     => ['weeks', 'months', 'length', 'timeline'],
        'long'         => ['duration', 'weeks', 'months'],
        'admission'    => ['enroll', 'enrolment', 'apply', 'registration', 'join', 'intake'],
        'enroll'       => ['admission', 'apply', 'registration', 'join'],
        'apply'        => ['admission', 'enroll', 'registration'],
        'job'          => ['career', 'placement', 'hiring', 'employment', 'internship'],
        'career'       => ['job', 'placement', 'internship'],
        'salary'       => ['earning', 'income', 'pay', 'package'],
        'location'     => ['address', 'campus', 'lahore', 'dha', 'visit'],
        'located'      => ['address', 'campus', 'lahore', 'dha', 'visit'],
        'address'      => ['location', 'campus', 'lahore', 'visit'],
        'visit'        => ['address', 'campus', 'lahore', 'hours'],
        'contact'      => ['phone', 'whatsapp', 'email', 'number', 'call'],
        'phone'        => ['contact', 'whatsapp', 'number', 'call'],
        'hours'        => ['opening', 'monday', 'saturday', 'timings'],
        'open'         => ['opening', 'hours', 'monday', 'saturday'],
        'kid'          => ['kids', 'children', 'child', 'camp', 'summer'],
        'child'        => ['kids', 'children', 'camp'],
        // Parents describe the child, not the category.
        'son'          => ['kids', 'children', 'child', 'camp'],
        'daughter'     => ['kids', 'children', 'child', 'camp'],
        'boy'          => ['kids', 'children', 'camp'],
        'girl'         => ['kids', 'children', 'camp'],
        'free'         => ['navttc', 'scholarship', 'government', 'funded'],
        'certificate'  => ['certification', 'certified', 'diploma', 'accredited'],
        'online'       => ['remote', 'virtual', 'zoom'],
        'timing'       => ['schedule', 'hours', 'opening', 'class', 'shift'],
        'requirement'  => ['prerequisite', 'eligibility', 'qualification'],
    ];

    public function __construct(
        private readonly Index $index,
        private readonly int $maxPages = 3,
        private readonly int $chunksPerPage = 2,
    ) {
    }

    /** Weight given to a synonym relative to a word the visitor actually typed. */
    private const SYNONYM_WEIGHT = 0.4;

    /**
     * @return list<array{chunk:Chunk,score:float,terms:list<string>}>
     */
    public function retrieve(string $question): array
    {
        // Terms the visitor typed that the site actually uses somewhere. Terms
        // absent from the corpus are excluded so they can't drag coverage down.
        $core = array_values(array_filter(
            array_unique(Text::tokenize($question)),
            fn (string $term): bool => $this->index->knowsTerm($term)
        ));

        $weights = [];
        foreach ($this->synonymsFor($question) as $synonym) {
            $weights[Text::stem($synonym)] = self::SYNONYM_WEIGHT;
        }
        // A synonym that is also a typed word keeps full weight.
        foreach ($core as $term) {
            $weights[$term] = 1.0;
        }

        $hits = $this->index->search($this->expand($question), self::CANDIDATE_POOL, $weights);
        if ($hits === []) {
            return [];
        }

        /** @var array<string,array{score:float,hits:list<array{chunk:Chunk,score:float,terms:list<string>}>}> $pages */
        $pages = [];
        foreach ($hits as $hit) {
            $url = $hit['chunk']->url;
            $pages[$url] ??= ['score' => 0.0, 'hits' => []];
            $pages[$url]['hits'][] = $hit;
        }

        foreach ($pages as $url => $page) {
            $scores = array_column($page['hits'], 'score');
            // Best passage dominates; the rest corroborate. A plain sum would let
            // a long page outrank a precise one just by being long.
            $base = max($scores) + 0.25 * (array_sum($scores) - max($scores));

            // Coverage is what rescues a rare term from a pile of common ones:
            // a page mentioning "cybersecurity" *and* "course" beats one that
            // matches only "course", "learn" and "long".
            $pages[$url]['score'] = $base * (0.35 + 0.65 * $this->coverage($page['hits'], $core));
        }

        uasort($pages, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $selected = [];
        foreach (array_slice($pages, 0, $this->maxPages, true) as $page) {
            usort($page['hits'], static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            foreach (array_slice($page['hits'], 0, $this->chunksPerPage) as $hit) {
                $selected[] = $hit;
            }
        }

        return $selected;
    }

    /**
     * Fraction of the visitor's own terms that this page matches anywhere.
     *
     * @param list<array{chunk:Chunk,score:float,terms:list<string>}> $hits
     * @param list<string>                                            $core
     */
    private function coverage(array $hits, array $core): float
    {
        if ($core === []) {
            return 1.0;
        }
        $matched = [];
        foreach ($hits as $hit) {
            foreach ($hit['terms'] as $term) {
                if (in_array($term, $core, true)) {
                    $matched[$term] = true;
                }
            }
        }

        return count($matched) / count($core);
    }

    /**
     * Appends known synonyms for terms the visitor used, so "how much does it
     * cost" also matches copy written as "fee".
     */
    public function expand(string $question): string
    {
        $extra = $this->synonymsFor($question);

        return $extra === [] ? $question : $question . ' ' . implode(' ', $extra);
    }

    /** @return list<string> */
    private function synonymsFor(string $question): array
    {
        $tokens = Text::tokenize($question);
        $extra  = [];
        foreach (self::SYNONYMS as $term => $synonyms) {
            if (in_array(Text::stem($term), $tokens, true)) {
                $extra = array_merge($extra, $synonyms);
            }
        }

        return array_values(array_unique($extra));
    }

    /**
     * Formats retrieved passages for the prompt. Each carries its URL so the
     * model can link the visitor to the page it answered from.
     *
     * @param list<array{chunk:Chunk,score:float}> $hits
     */
    public function formatContext(array $hits): string
    {
        if ($hits === []) {
            return '(No matching page content was found for this question.)';
        }

        $blocks = [];
        foreach ($hits as $i => $hit) {
            $chunk    = $hit['chunk'];
            $blocks[] = sprintf(
                "<source id=\"%d\" url=\"%s\" page=\"%s\">\n%s\n</source>",
                $i + 1,
                $chunk->url,
                $chunk->label(),
                $chunk->text
            );
        }

        return implode("\n\n", $blocks);
    }

    /**
     * A compact map of the whole site, included in every request so the model
     * can point a visitor at the right page even when retrieval misses.
     * Generated from the index, so it never drifts from the live site.
     */
    public function siteMap(): string
    {
        $lines = [];
        foreach ($this->index->pages() as $page) {
            $lines[] = '- ' . $page['title'] . ' — ' . $page['url'];
        }

        return implode("\n", $lines);
    }
}
