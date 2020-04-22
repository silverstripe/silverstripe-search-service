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
     * @param string $class
     * @param int $id
     * @return $this
     */
    public function removeDocument(string $class, int $id): self;

    /**
     * A hook for configuring the search service
     */
    public function configure(): void;


}
