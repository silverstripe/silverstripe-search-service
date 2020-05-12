<?php

namespace SilverStripe\SearchService\Extensions;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\BatchProcessorAware;
use SilverStripe\SearchService\Service\ConfigurationAware;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\ServiceAware;

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
     * @var bool
     * @config
     */
    private static $enable_indexer = true;

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
     * @param SearchServiceInterface $searchService
     * @param IndexConfiguration $config
     * @param BatchDocumentInterface $batchProcessor
     */
    public function __construct(
        SearchServiceInterface $searchService,
        IndexConfiguration $config,
        BatchDocumentInterface $batchProcessor
    ) {
        parent::__construct();
        $this->setSearchService($searchService);
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
     */
    public function requireDefaultRecords()
    {
        if (!$this->hasConfigured) {
            $this->getSearchService()->configure();
            $this->hasConfigured = true;
        }
    }

    /**
     * Index this record into search or queue if configured to do so
     *
     * @return void
     * @throws Exception
     */
    public function indexInSearch(): void
    {
        $document = DataObjectDocument::create($this->owner);
        $this->getBatchProcessor()->addDocuments([$document]);
    }

    /**
     * Remove this item from search
     */
    public function removeFromSearch(): void
    {
        $document = DataObjectDocument::create($this->owner);
        $this->getBatchProcessor()->removeDocuments([$document->getIdentifier()]);
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
     * When unpublishing this item, remove from search
     * @throws Exception
     */
    public function onAfterUnpublish(): void
    {
        $this->owner->removeFromSearch();
    }

    /**
     * Before deleting this record ensure that it is removed from search.
     * @throws Exception
     */
    public function onBeforeDelete()
    {
        $this->owner->removeFromSearch();
    }

}
