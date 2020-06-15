<?php


namespace SilverStripe\SearchService\Interfaces;

interface BatchDocumentInterface
{
    /**
     * @param DocumentInterface[] $items
     * @return $this
     */
    public function addDocuments(array $items): BatchDocumentInterface;

    /**
     * @param DocumentInterface[] $items
     * @return $this
     */
    public function removeDocuments(array $items): BatchDocumentInterface;
}
