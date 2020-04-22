<?php

namespace SilverStripe\SearchService\Service;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Map;
use SilverStripe\ORM\RelationList;

/**
 * Handles all the index management and communication with the search service. Note that
 * any checking of records should be performed by the caller of these methods as
 * no permission checking is done by this class
 */
class Indexer
{
    use Configurable;

    /**
     * Add the provided item to the search index.
     *
     * Callee should check whether this object should be indexed at all. Calls
     * {@link exportAttributesFromObject()} to determine what data should be
     * indexed
     *
     * @param DataObject $item
     *
     * @return $this
     */
    public function indexItem($item)
    {
        $searchIndexes = $this->getService()->initIndexes($item);
        $fields = $this->exportAttributesFromObject($item);

        foreach ($searchIndexes as $searchIndex) {
            $searchIndex->saveObject($fields->toArray());
        }

        return $this;
    }

    public function getService()
    {
        return Injector::inst()->get(SearchService::class);
    }

    /**
     * Index multiple items of the same class at a time.
     *
     * @param DataObject[] $items
     *
     * @return $this
     */
    public function indexItems($items)
    {
        $searchIndexes = $this->getService()->initIndexes($items->first());
        $data = [];

        foreach ($items as $item) {
            $data[] = $this->exportAttributesFromObject($item)->toArray();
        }

        foreach ($searchIndexes as $searchIndex) {
            $searchIndex->saveObjects($data);
        }

        return $this;
    }



    /**
     * Remove an item ID from the index. As this would usually be when an object
     * is deleted in Silverstripe we cannot rely on the object existing.
     *
     * @param string $itemClass
     * @param int $itemId
     */
    public function deleteItem($itemClass, $itemId)
    {
        $searchIndexes = $this->getService()->initIndexes($itemClass);
        $key =  strtolower(str_replace('\\', '_', $itemClass) . '_'. $itemId);

        foreach ($searchIndexes as $searchIndex) {
            $searchIndex->deleteObject($key);
        }
    }

    /**
     * Generates a unique ID for this item. If using a single index with
     * different dataobjects such as products and pages they potentially would
     * have the same ID. Uses the classname and the ID.
     *
     * @param DataObject $item
     *
     * @return string
     */
    public function generateUniqueID($item)
    {
        return strtolower(str_replace('\\', '_', get_class($item)) . '_'. $item->ID);
    }

    /**
     * @param DataObject $item
     *
     * @return array
     */
    public function getObject($item)
    {
        $id = $this->generateUniqueID($item);

        $indexes = $this->getService()->initIndexes($item);

        foreach ($indexes as $index) {
            try {
                $output = $index->getObject($id);
                if ($output) {
                    return $output;
                }
            } catch (Exception $ex) {
            }
        }
    }
}
