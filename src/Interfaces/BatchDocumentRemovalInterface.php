<?php

namespace SilverStripe\SearchService\Interfaces;

interface BatchDocumentRemovalInterface
{
    /**
     * @return int The number of removed documents from this call (should equal the previous total number of documents)
     */
    public function removeAllDocuments(string $indexName): int;
}
