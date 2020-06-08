<?php

namespace SilverStripe\SearchService\Interfaces;

use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Exception\SearchServiceException;

interface IndexingInterface extends BatchDocumentInterface
{

    /**
     * @param DocumentInterface $item
     * @return $this
     * @throws SearchServiceException
     */
    public function addDocument(DocumentInterface $item): self;

    /**
     * @param DocumentInterface $doc
     * @return $this
     * @throws SearchServiceException
     */
    public function removeDocument(DocumentInterface $doc): self;

    /**
     * @param string $id
     * @return array|null
     * @throws SearchServiceException
     */
    public function getDocument(string $id): ?array;

    /**
     * @param array $ids
     * @return array
     * @throws SearchServiceException
     */
    public function getDocuments(array $ids): array;

    /**
     * @param string $indexName
     * @param int|null $limit
     * @param int $offset
     * @return DocumentInterface[]
     * @throws SearchServiceException
     */
    public function listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array;

    /**
     * A hook for configuring the index service
     * @throws SearchServiceException
     */
    public function configure(): void;

    /**
     * @param string $field
     * @throws IndexConfigurationException
     */
    public function validateField(string $field): void;

}
