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
    use Injectable;

    /**
     * @param DocumentInterface[] $documents
     * @return $this
     * @throws Exception
     */
    public function addDocuments(array $documents): BatchDocumentInterface
    {
        $job = IndexJob::create($documents, IndexJob::METHOD_ADD);
        QueuedJobService::singleton()->queueJob($job);

        return $this;
    }

    /**
     * @param DocumentInterface[] $documents
     * @return $this
     * @throws Exception
     */
    public function removeDocuments(array $documents): BatchDocumentInterface
    {
        $job = IndexJob::create($documents, IndexJob::METHOD_DELETE);
        QueuedJobService::singleton()->queueJob($job);

        return $this;
    }

}
