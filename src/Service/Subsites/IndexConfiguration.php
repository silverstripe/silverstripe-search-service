<?php

namespace SilverStripe\SearchService\Service\Subsites;

use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\IndexConfiguration as CoreIndexConfiguration;

class IndexConfiguration extends CoreIndexConfiguration
{
    /**
     * @param DocumentInterface $doc
     * @return array
     */
    public function getIndexesForDocument(DocumentInterface $doc): array
    {
        $matches = [];
        $class = $doc->getSourceClass();
        $docSubsiteId = null;
        if ($doc instanceof DataObjectDocument) {
            $docSubsiteId = $doc->getDataObject()->SubsiteID ?? null;
        }

        foreach ($this->getIndexes() as $indexName => $data) {
            $classes = $data['includeClasses'] ?? [];
            $subsiteId = $data['subsite_id'] ?? 'all';
            if ($subsiteId !== 'all' && $docSubsiteId !== $subsiteId) {
                continue;
            }
            foreach ($classes as $candidate => $spec) {
                if ($spec === false) {
                    continue;
                }
                if ($class === $candidate || is_subclass_of($class, $candidate)) {
                    $matches[$indexName] = $data;
                    break;
                }
            }
        }

        return $matches;
    }
}
