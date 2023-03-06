<?php

namespace SilverStripe\SearchService\Service\Naive;

use SilverStripe\SearchService\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\IndexConfiguration;

class NaiveSearchService implements IndexingInterface, BatchDocumentRemovalInterface
{

    public function environmentizeIndex(string $indexName): string
    {
        $variant = IndexConfiguration::singleton()->getIndexVariant();

        if ($variant) {
            return sprintf('%s-%s', $variant, $indexName);
        }

        return $indexName;
    }

    public function addDocument(DocumentInterface $document): ?string
    {
        return null;
    }

    public function addDocuments(array $documents): array
    {
        return [];
    }

    public function removeDocuments(array $documents): array
    {
        return [];
    }

    public function removeAllDocuments(string $indexName): int
    {
        return 0;
    }

    public function getDocuments(array $ids): array
    {
        return [];
    }

    public function listDocuments(string $indexName, ?int $pageSize = null, int $currentPage = 0): array
    {
        return [];
    }

    public function validateField(string $field): void
    {
        // No value required
    }

    public function configure(): array
    {
        return [];
    }

    public function getDocument(string $id): ?DocumentInterface
    {
        return null;
    }

    public function getDocumentTotal(string $indexName): int
    {
        return 0;
    }

    public function removeDocument(DocumentInterface $document): ?string
    {
        return null;
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
