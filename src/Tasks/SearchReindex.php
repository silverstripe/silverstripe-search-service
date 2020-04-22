<?php

namespace SilverStripe\SearchService\Tasks;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\Versioned\Versioned;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\SearchService\Service\SearchService;

class SearchReindex extends BuildTask
{
    protected $title = 'Search Service Reindex';

    protected $description = 'Search Service Reindex';

    private static $segment = 'SearchReindex';

    private static $batch_size = 20;

    /**
     * @var SearchServiceInterface
     */
    private $searchService;

    public function __construct(SearchServiceInterface $searchService)
    {
        $this->setSearchService($searchService);
    }


    public function run($request)
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $targetClass = SiteTree::class;
        $additionalFiltering = '';

        if ($request->getVar('onlyClass')) {
            $targetClass = $request->getVar('onlyClass');
        }

        if ($request->getVar('filter')) {
            $additionalFiltering = $request->getVar('filter');
        }

        if ($request->getVar('forceAll')) {
            $items = Versioned::get_by_stage(
                $targetClass,
                Versioned::LIVE,
                $additionalFiltering
            );
        } else {
            $items = Versioned::get_by_stage(
                $targetClass,
                Versioned::LIVE,
                ($additionalFiltering)
                    ? $additionalFiltering
                    : 'SearchIndexed IS NULL OR SearchIndexed < (NOW() - INTERVAL 2 HOUR)'
            );
        }

        $count = 0;
        $skipped = 0;
        $errored = 0;
        $total = $items->count();
        $batchSize = $this->config()->get('batch_size');
        $batchesTotal = ($total > 0) ? (ceil($total / $batchSize)) : 0;

        echo sprintf(
            'Found %s pages remaining to index, will export in batches of %s, grouped by type. %s',
            $total,
            $batchSize,
            $batchesTotal,
            PHP_EOL
        );

        $pos = 0;

        if ($total < 1) {
            return;
        }

        $currentBatches = [];

        for ($i = 0; $i < $batchesTotal; $i++) {
            $limitedSize = $items->sort('ID', 'DESC')->limit($batchSize, $i * $batchSize);

            /* @var DataObject|SearchServiceExtension $item */
            foreach ($limitedSize as $item) {
                $pos++;

                echo '.';

                if ($pos % 50 == 0) {
                    echo sprintf(' [%s/%s]%s', $pos, $total, PHP_EOL);
                }

                // fetch the actual instance
                /* @var DataObject|SearchServiceExtension $instance */
                $instance = DataObject::get_by_id($item->ClassName, $item->ID);

                if (!$instance || !$instance->canIndexInSearch()) {
                    $skipped++;

                    continue;
                }

                $batchKey = get_class($item);

                if (!isset($currentBatches[$batchKey])) {
                    $currentBatches[$batchKey] = [];
                }

                $attributes = DocumentBuilder::create($item)->exportAttributes()->toArray();
                $currentBatches[$batchKey][] = $attributes;
                $item->touchSearchIndexedDate();
                $count++;

                if (count($currentBatches[$batchKey]) >= $batchSize) {
                    $this->indexBatch($currentBatches[$batchKey]);

                    unset($currentBatches[$batchKey]);

                    sleep(1);
                }
            }
        }

        foreach ($currentBatches as $class => $records) {
            if (count($currentBatches[$class]) > 0) {
                $this->indexbatch($currentBatches[$class]);

                sleep(1);
            }
        }

        Debug::message(sprintf(
            "Number of objects indexed: %s, Errors: %s, Skipped %s",
            $count,
            $errored,
            $skipped
        ));

    }

    /**
     * Index a batch of changes
     *
     * @param array $items
     *
     * @return bool
     */
    public function indexBatch($items)
    {
        $service = $this->getSearchService();

        try {
            $service->addDocuments($items);
            return true;
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);

            if (Director::isDev()) {
                Debug::message($e->getMessage());
            }

            return false;
        }
    }

    /**
     * @return SearchServiceInterface
     */
    public function getSearchService(): SearchServiceInterface
    {
        return $this->searchService;
    }

    /**
     * @param SearchServiceInterface $searchService
     * @return SearchReindex
     */
    public function setSearchService(SearchServiceInterface $searchService): SearchReindex
    {
        $this->searchService = $searchService;
        return $this;
    }


}
