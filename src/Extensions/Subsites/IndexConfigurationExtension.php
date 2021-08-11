<?php

namespace SilverStripe\SearchService\Extensions\Subsites;

use SilverStripe\Core\Extension;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Interfaces\DocumentInterface;

class IndexConfigurationExtension extends Extension
{
    /**
     * @param DocumentInterface $doc
     * @param array $indexes
     */
    public function updateIndexesForDocument(DocumentInterface $doc, array &$indexes): void
    {
        $docSubsiteId = null;
        if ($doc instanceof DataObjectDocument) {
            $docSubsiteId = $doc->getDataObject()->SubsiteID ?? null;
        }

        foreach ($indexes as $indexName => $data) {
            $subsiteId = $data['subsite_id'] ?? 'all';
            if ($subsiteId !== 'all' && $docSubsiteId !== $subsiteId) {
                unset($indexes[$indexName]);
            }
        }
    }
}
