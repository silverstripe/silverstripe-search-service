<?php

namespace SilverStripe\SearchService\Interfaces;

interface BatchDocumentInterface
{

    /**
     * @return array Array of IDs of the Documents added
     * @param DocumentInterface[] $documents
     */
    public function addDocuments(array $documents): array;

    /**
     * @param DocumentInterface[] $documents
     * @return array Array of IDs of the Documents removed
     */
    public function removeDocuments(array $documents): array;

}
