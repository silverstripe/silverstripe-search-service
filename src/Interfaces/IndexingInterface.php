<?php

namespace SilverStripe\SearchService\Interfaces;

use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Exception\IndexingServiceException;

interface IndexingInterface extends BatchDocumentInterface
{

    /**
     * @return string|null ID of the Document added
     * @throws IndexingServiceException
     */
    public function addDocument(DocumentInterface $document): ?string;

    /**
     * @return string|null ID of the Document removed
     * @throws IndexingServiceException
     */
    public function removeDocument(DocumentInterface $document): ?string;

    /**
     * @throws IndexingServiceException
     */
    public function getMaxDocumentSize(): int;

    /**
     * @throws IndexingServiceException
     */
    public function getDocument(string $id): ?DocumentInterface;

    /**
     * @return DocumentInterface[]
     * @throws IndexingServiceException
     */
    public function getDocuments(array $ids): array;

    /**
     * @return DocumentInterface[]
     * @throws IndexingServiceException
     */
    public function listDocuments(string $indexName, ?int $pageSize = null, int $currentPage = 0): array;

    /**
     * @return int
     * @throws IndexingServiceException
     */
    public function getDocumentTotal(string $indexName): int;

    /**
     * A hook for configuring the index service
     *
     * @return array Current Schemas from Elastic [indexName: [field configuration]]
     * @throws IndexingServiceException
     */
    public function configure(): array;

    /**
     * @throws IndexConfigurationException
     */
    public function validateField(string $field): void;

    /**
     * For display in the CMS
     */
    public function getExternalURL(): ?string;

    /**
     * Text to display for the above URL
     */
    public function getExternalURLDescription(): ?string;

    /**
     * URL to display in the CMS to link to documentation
     */
    public function getDocumentationURL(): ?string;

}
