<?php

namespace SilverStripe\SearchService\Extensions;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\DataObject\DataObjectBatchProcessor;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\Traits\BatchProcessorAware;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Traits\ServiceAware;
use SilverStripe\Versioned\Versioned;

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
    use ConfigurationAware;
    use BatchProcessorAware;

    /**
     * @var array
     */
    private static $db = [
        'SearchIndexed' => 'Datetime'
    ];

    /**
     * @var bool
     */
    private $hasConfigured = false;

    /**
     * SearchServiceExtension constructor.
     * @param IndexingInterface $searchService
     * @param IndexConfiguration $config
     * @param DataObjectBatchProcessor $batchProcessor
     */
    public function __construct(
        IndexingInterface $searchService,
        IndexConfiguration $config,
        DataObjectBatchProcessor $batchProcessor
    ) {
        parent::__construct();
        $this->setIndexService($searchService);
        $this->setConfiguration($config);
        $this->setBatchProcessor($batchProcessor);
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        if ($this->getConfiguration()->isEnabled()) {
            $fields->addFieldsToTab('Root.Main', [
                ReadonlyField::create('SearchIndexed', _t(__CLASS__.'.LastIndexed', 'Last indexed in search'))
            ]);
        }
    }

    /**
     * On dev/build ensure that the indexer settings are up to date.
     * @throws IndexingServiceException
     */
    public function requireDefaultRecords()
    {
        if (!$this->hasConfigured) {
            $this->getIndexService()->configure();
            $this->hasConfigured = true;
        }
    }
    /**
     * Index this record into search or queue if configured to do so
     *
     * @return void
     */
    public function addToIndexes(): void
    {
        $document = DataObjectDocument::create($this->owner);
        $this->getBatchProcessor()->addDocuments([$document]);
    }

    /**
     * Remove this item from search
     */
    public function removeFromIndexes(): void
    {
        $document = DataObjectDocument::create($this->owner);
        $this->getBatchProcessor()->removeDocuments([$document]);
    }

    /**
     * When publishing the page, push this data to Indexer. The data
     * which is sent to search is the rendered template from the front end.
     * @throws Exception
     */
    public function onAfterPublish()
    {
        $this->owner->addToIndexes();
    }

    /**
     * When unpublishing this item, remove from search
     */
    public function onAfterUnpublish(): void
    {
        $this->owner->removeFromIndexes();
    }

    /**
     * Before deleting this record ensure that it is removed from search.
     * @throws Exception
     */
    public function onAfterDelete()
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            $this->owner->removeFromIndexes();
        }
    }
}
