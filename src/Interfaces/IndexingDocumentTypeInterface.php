<?php

namespace SilverStripe\SearchService\Interfaces;

interface IndexingDocumentTypeInterface
{

    /**
     * Set collection of document types (class names) that should be cleared from Elastic index
     */
    public function setDocumentTypes(array $types): self;

}
