<?php

namespace SilverStripe\SearchService\Jobs;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\Indexer;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Index an item (or multiple items) into search async. This method works well
 * for performance and batching large indexes
 *
 * @property array $documents
 * @property array $remainingDocuments
 * @property int $method
 * @property int $batchSize
 * @property bool $processDependencies
 */
class IndexJob extends AbstractQueuedJob implements QueuedJob
{
    use Injectable;
    use Extensible;

    /**
     * @param DocumentInterface[] $documents
     * @param int $method
     * @param int $batchSize
     * @param bool $processDependencies
     */
    public function __construct(
        array $documents = [],
        int $method = Indexer::METHOD_ADD,
        ?int $batchSize = null,
        bool $processDependencies = true
    ) {
        $this->documents = $documents;
        $this->method = $method;
        $this->batchSize = $batchSize;
        $this->processDependencies = $processDependencies;

        parent::__construct();
    }

    public function setup()
    {
        if (!$this->batchSize) {
            // If we don't have a batchSize, then we're just processing everything in one go
            $this->totalSteps = 1;
        } else {
            // There could be 0 documents. If that's the case, then there's zero steps
            $this->totalSteps = $this->documents
                ? ceil(count($this->documents) / $this->batchSize)
                : 0;
        }

        $this->currentStep = 0;
        $this->remainingDocuments = $this->documents;

        parent::setup();
    }

    /**
     * Defines the title of the job.
     *
     * @return string
     */
    public function getTitle()
    {
        return sprintf(
            'Search service %s %s documents',
            $this->method === Indexer::METHOD_DELETE ? 'removing' : 'adding',
            sizeof($this->documents)
        );
    }

    /**
     * @return int
     */
    public function getJobType()
    {
        return QueuedJob::IMMEDIATE;
    }

    /**
     * Lets process a single node
     */
    public function process()
    {
        // It is possible that this Job is queued with no documents to be updated. If so, just mark it as complete
        if ($this->totalSteps === 0) {
            $this->isComplete = true;

            return;
        }

        $remainingDocuments = $this->remainingDocuments;
        // Splice a bunch of Documents from the start of the remaining documents
        $documentToProcess = array_splice($remainingDocuments, 0, $this->batchSize);

        $indexer = Indexer::create($documentToProcess, $this->method, $this->batchSize);
        $indexer->setProcessDependencies($this->processDependencies);

        $this->extend('onBeforeProcess');
        $indexer->processNode();
        $this->extend('onAfterProcess');

        // Save away whatever Documents are still remaining
        $this->remainingDocuments = $remainingDocuments;
        $this->currentStep++;

        if ($this->currentStep >= $this->totalSteps) {
            $this->isComplete = true;
        }
    }
}
