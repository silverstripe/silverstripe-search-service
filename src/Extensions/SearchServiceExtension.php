<?php

namespace SilverStripe\SearchService\Extensions;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\SearchService\Jobs\DeleteItemJob;
use SilverStripe\SearchService\Jobs\IndexItemJob;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\SearchService\Service\SearchService;

class SearchServiceExtension extends DataExtension
{
    use Configurable;

    /**
     *
     */
    private static $enable_indexer = true;

    /**
     *
     */
    private static $use_queued_indexing = false;

    private static $db = [
        'SearchIndexed' => 'Datetime'
    ];

    /**
     * @return bool
     */
    public function indexEnabled(): bool
    {
        return $this->config('enable_indexer') ? true : false;
    }

    /**
     * @param FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->indexEnabled()) {
            $fields->addFieldsToTab('Root.Main', [
                ReadonlyField::create('SearchIndexed', _t(__CLASS__.'.LastIndexed', 'Last indexed in search'))
            ]);
        }
    }

    /**
     * On dev/build ensure that the indexer settings are up to date.
     */
    public function requireDefaultRecords()
    {
        $search = Injector::inst()->create(SearchService::class);
        $search->build();
    }

    /**
     * Returns whether this object should be indexed search.
     */
    public function canIndexInSearch(): bool
    {
        if ($this->owner->hasField('ShowInSearch')) {
            return $this->owner->ShowInSearch;
        }

        return true;
    }

    /**
     * When publishing the page, push this data to Indexer. The data
     * which is sent to search is the rendered template from the front end.
     */
    public function onAfterPublish()
    {
        $this->owner->indexInSearch();
    }

    /**
     * Update the indexed date for this object.
     */
    public function touchSearchIndexedDate()
    {
        $schema = DataObject::getSchema();
        $table = $schema->tableForField($this->owner->ClassName, 'SearchIndexed');

        if ($table) {
            DB::query(sprintf('UPDATE %s SET SearchIndexed = NOW() WHERE ID = %s', $table, $this->owner->ID));

            if ($this->owner->hasExtension(Versioned::class) && $this->owner->hasStages()) {
                DB::query(sprintf('UPDATE %s_Live SET SearchIndexed = NOW() WHERE ID = %s', $table, $this->owner->ID));
            }
        }
    }

    /**
     * Index this record into search or queue if configured to do so
     *
     * @return bool
     */
    public function indexInSearch(): bool
    {
        if ($this->owner->indexEnabled() && min($this->owner->invokeWithExtensions('canIndexInSearch')) == false) {
            return false;
        }

        if ($this->config()->get('use_queued_indexing')) {
            $indexJob = new IndexItemJob(get_class($this->owner), $this->owner->ID);
            QueuedJobService::singleton()->queueJob($indexJob);

            return true;
        } else {
            return $this->doImmediateIndexInSearch();
        }
    }

    /**
     * Index this record into search
     *
     * @return bool
     */
    public function doImmediateIndexInSearch()
    {
        $indexer = Injector::inst()->get(Indexer::class);

        try {
            $indexer->indexItem($this->owner);

            $this->touchSearchIndexedDate();

            return true;
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);

            return false;
        }
    }

    /**
     * When unpublishing this item, remove from search
     */
    public function onAfterUnpublish()
    {
        if ($this->owner->indexEnabled()) {
            $this->removeFromSearch();
        }
    }

    /**
     * Remove this item from search
     */
    public function removeFromSearch()
    {
        $indexer = Injector::inst()->get(Indexer::class);

        if ($this->config()->get('use_queued_indexing')) {
            $indexDeleteJob = new DeleteItemJob(get_class($this->owner), $this->owner->ID);
            QueuedJobService::singleton()->queueJob($indexDeleteJob);
        } else {
            try {
                $indexer->deleteItem(get_class($this->owner), $this->owner->ID);

                $this->touchSearchIndexedDate();
            } catch (Exception $e) {
                Injector::inst()->create(LoggerInterface::class)->error($e);
            }
        }
    }

    /**
     * Before deleting this record ensure that it is removed from search.
     */
    public function onBeforeDelete()
    {
        if ($this->owner->indexEnabled()) {
            $this->removeFromSearch();
        }
    }

    /**
     * @return array
     */
    public function getSearchIndexes()
    {
        $indexer = Injector::inst()->get(Indexer::class);

        return $indexer->getService()->initIndexes($this->owner);
    }
}
