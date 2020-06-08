<?php

namespace SilverStripe\SearchService\Interfaces;

use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Exception\IndexingServiceException;

interface IndexingInterface extends BatchDocumentInterface
{

    /**
     * @param DocumentInterface $item
     * @return $this
     * @throws IndexingServiceException
     */
    public function addDocument(DocumentInterface $item): self;

    /**
     * @param DocumentInterface $doc
     * @return $this
     * @throws IndexingServiceException
     */
    public function removeDocument(DocumentInterface $doc): self;

    /**
     * @param string $id
     * @return array|null
     * @throws IndexingServiceException
     */
    public function getDocument(string $id): ?array;

    /**
     * @param array $ids
     * @return array
     * @throws IndexingServiceException
     */
    public function getDocuments(array $ids): array;

    /**
     * @param string $indexName
     * @param int|null $limit
     * @param int $offset
     * @return DocumentInterface[]
     * @throws IndexingServiceException
     */
    public function listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array;

    /**
     * @param string $indexName
     * @return int
     * @throws IndexingServiceException
     */
    public function getDocumentTotal(string $indexName): int;

    /**
     * A hook for configuring the index service
     * @throws IndexingServiceException
     */
    public function configure(): void;

    /**
     * @param string $field
     * @throws IndexConfigurationException
     */
    public function validateField(string $field): void;

}
