<?php

namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Exception;

class BatchProcessor implements BatchDocumentInterface
{
    use Injectable;
    use ConfigurationAware;

    /**
     * BatchProcessor constructor.
     * @param IndexConfiguration $configuration
     */
    public function __construct(IndexConfiguration $configuration)
    {
        $this->setConfiguration($configuration);
    }

    /**
     * @param DocumentInterface[] $documents
     * @return $this
     * @throws Exception
     */
    public function addDocuments(array $documents): BatchDocumentInterface
    {
        $job = IndexJob::create($documents, Indexer::METHOD_ADD);
        $this->run($job);

        return $this;
    }

    /**
     * @param DocumentInterface[] $documents
     * @return $this
     * @throws Exception
     */
    public function removeDocuments(array $documents): BatchDocumentInterface
    {
        $job = IndexJob::create($documents, Indexer::METHOD_DELETE);
        $this->run($job);

        return $this;
    }

    /**
     * @param QueuedJob $job
     * @throws ValidationException
     */
    protected function run(QueuedJob $job): void
    {
        if ($this->getConfiguration()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }
    }
}
