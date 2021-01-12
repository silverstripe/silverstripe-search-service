<?php

namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Interfaces\DocumentInterface;

class SubsiteIndexConfigurationExtension extends Extension
{
    /**
     * @param DocumentInterface $doc
     * @param array $indexes
     * @return array
     */
    public function updateIndexesForDocument(DocumentInterface $doc, array $indexes): array
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

        return $indexes;
    }
}
