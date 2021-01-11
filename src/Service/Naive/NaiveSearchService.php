<?php

namespace SilverStripe\SearchService\Services\Naive;

use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;

class NaiveSearchService implements IndexingInterface, BatchDocumentRemovalInterface
{
    public function addDocument(DocumentInterface $item): IndexingInterface
    {
        return $this;
    }

    public function addDocuments(array $items): BatchDocumentInterface
    {
        return $this;
    }

    public function removeDocuments(array $items): BatchDocumentInterface
    {
        return $this;
    }

    public function removeAllDocuments(string $indexName): int
    {
        return 0;
    }

    public function getDocuments(array $ids): array
    {
        return [];
    }

    public function listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array
    {
        return [];
    }

    public function validateField(string $field): void
    {
        return;
    }

    public function configure(): void
    {
        return;
    }

    public function getDocument(string $id): ?DocumentInterface
    {
        return null;
    }

    public function getDocumentTotal(string $indexName): int
    {
        return 0;
    }

    public function removeDocument(DocumentInterface $doc): IndexingInterface
    {
        return $this;
    }

    public function getMaxDocumentSize(): int
    {
        return 0;
    }

    public function getExternalURL(): ?string
    {
        return null;
    }

    public function getExternalURLDescription(): ?string
    {
        return null;
    }

    public function getDocumentationURL(): ?string
    {
        return null;
    }
}
