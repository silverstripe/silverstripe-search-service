<?php


namespace SilverStripe\SearchService\Tasks;


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

class SearchClearIndex extends BuildTask
{
    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;

    protected $title = 'Search Service Clear Index';

    protected $description = 'Search Service Clear Index';

    private static $segment = 'SearchClearIndex';

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
        $this->setIndexService($searchService);
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

        $service = $this->getIndexService();
        $targetIndex = $request->getVar('index');
        if (!$targetIndex) {
            echo "Must specify an index in the 'index' parameter." . PHP_EOL;
            die();
        }
        die(var_dump($service->listDocuments($targetIndex)));
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
