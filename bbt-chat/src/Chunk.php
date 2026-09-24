<?php
declare(strict_types=1);

namespace BBTChat;

/** One retrievable passage: a slice of a page, with enough context to cite it. */
final class Chunk
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $pageTitle,
        /** Heading trail above this passage, e.g. ["Course outline", "Module 3"]. */
        public readonly array $headings,
        public readonly string $text,
    ) {
    }

    /** Heading path plus body, which is what actually gets indexed and sent to the model. */
    public function searchableText(): string
    {
        return trim($this->pageTitle . "\n" . implode(' > ', $this->headings) . "\n" . $this->text);
    }

    public function label(): string
    {
        return $this->headings === []
            ? $this->pageTitle
            : $this->pageTitle . ' > ' . implode(' > ', $this->headings);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'url'       => $this->url,
            'pageTitle' => $this->pageTitle,
            'headings'  => $this->headings,
            'text'      => $this->text,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['id'],
            (string) $data['url'],
            (string) $data['pageTitle'],
            array_values((array) ($data['headings'] ?? [])),
            (string) $data['text'],
        );
    }
}
