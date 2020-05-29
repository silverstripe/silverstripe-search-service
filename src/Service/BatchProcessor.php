<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Jobs\IndexJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Exception;

class BatchProcessor implements BatchDocumentInterface
{
    use ConfigurationAware;
    use ServiceAware;
    use Injectable;

    /**
     * BatchProcessor constructor.
     * @param IndexingInterface $service
     * @param IndexConfiguration $configuration
     */
    public function __construct(IndexingInterface $service, IndexConfiguration $configuration)
    {
        $this->setSearchService($service);
        $this->setConfiguration($configuration);
    }

    /**
     * @param DocumentInterface[] $documents
     * @return $this
     * @throws Exception
     */
    public function addDocuments(array $documents): BatchDocumentInterface
    {
        if ($this->getConfiguration()->isUsingQueuedJobs()) {
            $job = IndexJob::create($documents, IndexJob::METHOD_ADD);
            QueuedJobService::singleton()->queueJob($job);
        } else {
            $this->getSearchService()->addDocuments($documents);
        }

        return $this;
    }

    /**
     * @param DocumentInterface[] $documents
     * @return $this
     * @throws Exception
     */
    public function removeDocuments(array $documents): BatchDocumentInterface
    {
        if ($this->getConfiguration()->isUsingQueuedJobs()) {
            $job = IndexJob::create($documents, IndexJob::METHOD_DELETE);
            QueuedJobService::singleton()->queueJob($job);
        } else {
            $this->getSearchService()->addDocuments($documents);
        }

        return $this;
    }

}
