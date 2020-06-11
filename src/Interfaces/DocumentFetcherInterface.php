<?php


namespace SilverStripe\SearchService\Interfaces;

interface DocumentFetcherInterface
{
    /**
     * @param int $limit
     * @param int $offset
     * @return DocumentInterface[]
     */
    public function fetch(int $limit, int $offset): array;

    /**
     * @return int
     */
    public function getTotalDocuments(): int;

    /**
     * @param array $data
     * @return DocumentInterface|null
     */
    public function createDocument(array $data): ?DocumentInterface;

}
