<?php

namespace SilverStripe\SearchService\Tasks;

use InvalidArgumentException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Service\Traits\BatchProcessorAware;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\Traits\ServiceAware;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class SearchClearIndex extends BuildTask
{

    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;

    protected $title = 'Search Service Clear Index'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    protected $description = 'Search Service Clear Index'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    private static $segment = 'SearchClearIndex'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    private ?BatchDocumentInterface $batchProcessor = null;

    public function __construct(
        IndexingInterface $searchService,
        IndexConfiguration $config,
        BatchDocumentInterface $batchProcessor
    ) {
        parent::__construct();

        $this->setIndexService($searchService);
        $this->setConfiguration($config);
        $this->setBatchProcessor($batchProcessor);
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $targetIndex = $request->getVar('index');

        if (!$targetIndex) {
            throw new InvalidArgumentException("Must specify an index in the 'index' parameter.");
        }

        $job = ClearIndexJob::create($targetIndex);

        if ($this->getConfiguration()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }
    }

}
