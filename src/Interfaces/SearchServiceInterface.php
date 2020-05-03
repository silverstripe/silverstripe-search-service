<?php

namespace SilverStripe\SearchService\Interfaces;

use SilverStripe\ORM\DataObject;

interface SearchServiceInterface
{

    /**
     * @param DataObject[] $items
     * @return $this
     */
    public function addDocuments(array $items): self;

    /**
     * @param DataObject $item
     * @return $this
     */
    public function addDocument(DataObject $item): self;

    /**
     * @param DataObject $item
     * @return $this
     */
    public function removeDocument(DataObject $item): self;

    /**
     * @param DataObject[] $items
     * @return $this
     */
    public function removeDocuments(array $items): self;

    /**
     * @param string $id
     * @return array|null
     */
    public function getDocument(string $id): ?array;

    /**
     * @param array $ids
     * @return $this
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
    public function normaliseField(string $field): array;
}
