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
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Limitable;
use SilverStripe\ORM\Sortable;
use SilverStripe\ORM\SS_List;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\DocumentFetcherRegistry;
use SilverStripe\SearchService\Service\ServiceAware;
use SilverStripe\Versioned\Versioned;

class SearchReindex extends BuildTask
{
    use ServiceAware;

    protected $title = 'Search Service Reindex';

    protected $description = 'Search Service Reindex';

    private static $segment = 'SearchReindex';

    /**
     * @var int
     * @config
     */
    private static $batch_size = 20;

    /**
     * @var bool
     * @config
     */
    private static $use_queued_indexing = false;

    /**
     * SearchReindex constructor.
     * @param SearchServiceInterface $searchService
     */
    public function __construct(SearchServiceInterface $searchService)
    {
        $this->setSearchService($searchService);
    }


    public function run($request)
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $service = $this->getSearchService();
        $targetClass = $request->getVar('onlyClass');
        $classes = $targetClass ? [$targetClass] : $service->getSearchableClasses();

        /* @var DocumentFetcherRegistry $registry */
        $registry = Injector::inst()->get(DocumentFetcherRegistry::class);
        /* @var DocumentFetcherInterface[] $fetchers */
        $fetchers = [];
        foreach ($classes as $class) {
            $fetcher = $registry->getFetcherForType($class);
            if ($fetcher) {
                $fetchers[$class] = $fetcher;
            }
        }

        $count = 0;
        $skipped = 0;
        $errored = 0;
        $batchSize = $this->config()->get('batch_size');
        foreach ($fetchers as $class => $fetcher) {
            $total = $fetcher->getTotalDocuments($class);
            echo sprintf('Building %s documents for %s', $total, $class) . PHP_EOL;
            $batchesTotal = ($total > 0) ? (ceil($total / $batchSize)) : 0;

            echo sprintf(
                'Found %s documents remaining to index, will export in batches of %s, grouped by type. (%s total) %s',
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
                $batch = $fetcher->fetch($class, $batchSize, $i * $batchSize);
                foreach ($batch as $document) {
                    $pos++;
                    echo '.';
                    if ($pos % 50 == 0) {
                        echo sprintf(' [%s/%s]%s', $pos, $total, PHP_EOL);
                    }
                    if (!$document->shouldIndex()) {
                        $skipped++;
                        continue;
                    }

                    $batchKey = get_class($document);

                    if (!isset($currentBatches[$batchKey])) {
                        $currentBatches[$batchKey] = [];
                    }

                    $currentBatches[$batchKey][] = $document;
                    $count++;

                    if (count($currentBatches[$batchKey]) >= $batchSize) {
                        $this->indexBatch($currentBatches[$batchKey]);
                        unset($currentBatches[$batchKey]);
                        sleep(1);
                    }
                }
            }

            foreach ($currentBatches as $class => $documents) {
                if (count($currentBatches[$class]) > 0) {
                    $this->indexbatch($currentBatches[$class]);
                }
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
     * @param DocumentInterface[] $documents
     *
     * @return bool
     */
    public function indexBatch(array $documents): bool
    {
        $service = $this->getSearchService();

        try {
            $service->addDocuments($documents);
            foreach ($documents as $document) {
                $document->markIndexed();
            }
            return true;
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);

            if (Director::isDev()) {
                Debug::message($e->getMessage());
            }

            return false;
        }
    }

}
