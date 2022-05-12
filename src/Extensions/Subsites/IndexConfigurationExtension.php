<?php

namespace SilverStripe\SearchService\Extensions\Subsites;

use SilverStripe\Core\Extension;
use SilverStripe\SearchService\Interfaces\DataObjectDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;

class IndexConfigurationExtension extends Extension
{

    public function updateIndexesForDocument(DocumentInterface $doc, array &$indexes): void
    {
        // Skip if document object does not implement DataObject interface
        if (!$doc instanceof DataObjectDocumentInterface) {
            return;
        }

        $docSubsiteId = $doc->getDataObject()->SubsiteID ?? 0;

        if ((int) $docSubsiteId === 0) {
            $this->updateDocumentWithoutSubsite($doc, $indexes);
        } else {
            $this->updateDocumentWithSubsite($indexes, $docSubsiteId);
        }
    }

    /**
     * DataObject does not have a defined SubsiteID. So if the developer explicitly defined the dataObject to be
     * included in the Subsite Index configuration then allow the dataObject to be added in.
     */
    protected function updateDocumentWithoutSubsite(DocumentInterface $doc, array &$indexes): void
    {
        foreach ($indexes as $indexName => $data) {
            // DataObject explicitly defined on Subsite index definition
            $explicitClasses = $data['includeClasses'] ?? [];

            if (!isset($explicitClasses[$doc->getDataObject()->ClassName])) {
                unset($indexes[$indexName]);

                break;
            }
        }
    }

    protected function updateDocumentWithSubsite(array &$indexes, int $docSubsiteId): void
    {
        foreach ($indexes as $indexName => $data) {
            $subsiteId = $data['subsite_id'] ?? 'all';

            if ($subsiteId !== 'all' && $docSubsiteId !== (int)$subsiteId) {
                unset($indexes[$indexName]);
            }
        }
    }

}
