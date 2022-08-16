<?php

namespace SilverStripe\SearchService\Interfaces;

interface DocumentFetcherInterface
{

    /**
     * @return DocumentInterface[]
     */
    public function fetch(int $limit, int $offset): array;

    public function getTotalDocuments(): int;

    public function createDocument(array $data): ?DocumentInterface;

}
