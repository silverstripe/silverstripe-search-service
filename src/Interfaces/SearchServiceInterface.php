<?php

namespace SilverStripe\SearchService\Interfaces;

interface SearchServiceInterface extends BatchDocumentInterface
{

    /**
     * @param DocumentInterface $item
     * @return $this
     */
    public function addDocument(DocumentInterface $item): self;

    /**
     * @param string $id
     * @return $this
     */
    public function removeDocument(string $d): self;

    /**
     * @param string $id
     * @return array|null
     */
    public function getDocument(string $id): ?array;

    /**
     * @param array $ids
     * @return array
     */
    public function getDocuments(array $ids): array;

    /**
     * A hook for configuring the search service
     */
    public function configure(): void;

    /**
     * @return string[]
     */
    public function getSearchableClasses(): array;

    /**
     * @param string $field
     * @return array
     */
    public function normaliseField(string $field): string;

}
