<?php

namespace SilverStripe\SearchService\Tasks;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\BatchProcessorAware;
use SilverStripe\SearchService\Service\ConfigurationAware;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\ServiceAware;
use InvalidArgumentException;

class SearchReindex extends BuildTask
{
    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;

    protected $title = 'Search Service Reindex';

    protected $description = 'Search Service Reindex';

    private static $segment = 'SearchReindex';

    /**
     * @var BatchDocumentInterface
     */
    private $batchProcessor;

    /**
     * SearchReindex constructor.
     * @param IndexingInterface $searchService
     * @param IndexConfiguration $config
     * @param BatchDocumentInterface $batchProcesor
     */
    public function __construct(
        IndexingInterface $searchService,
        IndexConfiguration $config,
        BatchDocumentInterface $batchProcesor
    )
    {
        parent::__construct();
        $this->setSearchService($searchService);
        $this->setConfiguration($config);
        $this->setBatchProcessor($batchProcesor);
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $service = $this->getSearchService();
        $targetClass = $request->getVar('onlyClass');
        $classes = $targetClass ? [$targetClass] : $service->getSearchableClasses();

        /* @var DocumentFetchCreatorRegistry $registry */
        $registry = Injector::inst()->get(DocumentFetchCreatorRegistry::class);

        $until = strtotime(time(), '-' . $this->getConfiguration()->getSyncInterval());

        /* @var DocumentFetcherInterface[] $fetchers */
        $fetchers = [];
        foreach ($classes as $class) {
            $fetcher = $registry->getFetcher($class, $until);
            if ($fetcher) {
                $fetchers[$class] = $fetcher;
            }
        }

        $count = 0;
        $errored = 0;
        $batchSize = $this->getConfiguration()->getBatchSize();

        $allDocumentTotal = array_reduce($fetchers, function ($total, $fetcher) {
            /* @var DocumentFetcherInterface $fetcher */
            return $total + $fetcher->getTotalDocuments();
        }, 0);

        echo sprintf(
            "Syncing %s total documents across %s content types%s",
            $allDocumentTotal,
            sizeof($fetchers),
            PHP_EOL
        );

        foreach ($fetchers as $class => $fetcher) {
            $total = $fetcher->getTotalDocuments();
            echo sprintf('Building %s documents for %s', $total, $class) . PHP_EOL;
            $batchesTotal = ($total > 0) ? (ceil($total / $batchSize)) : 0;
            $pos = 1;
            foreach ($this->chunk($fetcher, $batchSize) as $chunk) {
                echo sprintf(' [%s/%s]%s', $pos, $batchesTotal, PHP_EOL);
                try {
                    $this->getBatchProcessor()->addDocuments($chunk);
                    $count += $batchSize;
                } catch (Exception $e) {
                    $errored++;
                    echo sprintf("ERROR: %s", $e->getMessage());
                    if (!Director::isDev()) {
                        Injector::inst()->get(LoggerInterface::class)
                            ->error($e);
                    }
                }
                $pos++;
            }
        }

        Debug::message(sprintf(
            "Number of objects indexed: %s, Errors: %s",
            $count,
            $errored,
        ));
    }

    /**
     * @param DocumentFetcherInterface $fetcher
     * @param int $chunkSize
     * @return iterable
     * @see https://github.com/silverstripe/silverstripe-framework/pull/8940/files
     */
    private function chunk(DocumentFetcherInterface $fetcher, int $chunkSize = 100): iterable
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException(sprintf(
                '%s::%s: chunkSize must be greater than or equal to 1',
                __CLASS__,
                __METHOD__
            ));
        }

        $currentChunk = 0;
        while ($chunk = $fetcher->fetch($chunkSize, $chunkSize * $currentChunk)) {
            foreach ($chunk as $item) {
                yield $item;
            }

            if (sizeof($chunk) < $chunkSize) {
                break;
            }
            $currentChunk++;
        }
    }

}
