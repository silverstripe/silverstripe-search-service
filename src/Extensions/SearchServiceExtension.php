<?php

namespace SilverStripe\SearchService\Extensions;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\ServiceAware;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\SearchService\Jobs\DeleteJob;
use SilverStripe\SearchService\Jobs\IndexJob;

/**
 * The extension that provides implicit indexing features to dataobjects
 *
 * @property DataObject|SearchServiceExtension $owner
 */
class SearchServiceExtension extends DataExtension
{
    use Configurable;
    use Injectable;
    use ServiceAware;

    /**
     * @var bool
     * @config
     */
    private static $enable_indexer = true;

    /**
     * @var bool
     * @config
     */
    private static $use_queued_indexing = false;

    private static $db = [
        'SearchIndexed' => 'Datetime'
    ];

    private $hasConfigured = false;

    /**
     * SearchServiceExtension constructor.
     * @param SearchServiceInterface $searchService
     */
    public function __construct(SearchServiceInterface $searchService)
    {
        parent::__construct();
        $this->setSearchService($searchService);
    }

    /**
     * @return bool
     */
    public function indexEnabled(): bool
    {
        return $this->config('enable_indexer') ? true : false;
    }

    /**
     * @param FieldList $fields
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
        if (!$this->hasConfigured) {
            $this->getSearchService()->configure();
            $this->hasConfigured = true;
        }
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
     * Index this record into search or queue if configured to do so
     *
     * @return bool
     * @throws Exception
     */
    public function indexInSearch(): bool
    {
        if ($this->owner->indexEnabled() && min($this->owner->invokeWithExtensions('canIndexInSearch')) == false) {
            return false;
        }

        if ($this->config()->get('use_queued_indexing')) {
            $indexJob = new IndexJob(get_class($this->owner), $this->owner->ID);
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
        try {
            $this->getSearchService()->addDocument($this->owner);

            $this->touchSearchIndexedDate();

            return true;
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);

            if (Director::isDev()) {
                throw $e;
            }

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
        if ($this->config()->get('use_queued_indexing')) {
            $indexDeleteJob = new DeleteJob(get_class($this->owner), $this->owner->ID);
            QueuedJobService::singleton()->queueJob($indexDeleteJob);
        } else {
            try {
                $this->getSearchService()->removeDocument($this->owner);

                $this->touchSearchIndexedDate();
            } catch (Exception $e) {
                Injector::inst()->create(LoggerInterface::class)->error($e);
                if (Director::isDev()) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Generates a unique ID for this item. If using a single index with
     * different dataobjects such as products and pages they potentially would
     * have the same ID. Uses the classname and the ID.
     **
     * @return string
     */
    public function generateSearchUUID(): string
    {
        return strtolower(str_replace('\\', '_', get_class($this->owner)) . '_'. $this->owner->ID);
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

}
