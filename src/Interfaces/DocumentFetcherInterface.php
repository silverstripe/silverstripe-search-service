<?php


namespace SilverStripe\SearchService\Interfaces;

interface DocumentFetcherInterface
{
    /**
     * @param string $type
     * @return bool
     */
    public function appliesTo(string $type): bool;

    /**
     * @param string|null $type
     * @param int $limit
     * @param int $offset
     * @return DocumentInterface[]
     */
    public function fetch(string $type, int $limit, int $offset): array;

    /**
     * @param string $type
     * @return int
     */
    public function getTotalDocuments(string $type): int;

}
