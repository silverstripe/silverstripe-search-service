<?php

namespace SilverStripe\SearchService\Jobs;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\Indexer;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Index an item (or multiple items) into search async. This method works well
 * for performance and batching large indexes
 *
 * @property Indexer $indexer
 * @property DocumentInterface[] $documents
 * @property int $method
 */
class IndexJob extends AbstractQueuedJob implements QueuedJob
{
    use Injectable;

    /**
     * @param DocumentInterface[] $documents
     * @param int $method
     * @param int $batchSize
     */
    public function __construct(
        array $documents = [],
        int $method = Indexer::METHOD_ADD,
        ?int $batchSize = null
    ) {
        parent::__construct();
        $this->documents = $documents;
        $this->method = $method;
        $this->indexer = Indexer::create($documents, $method, $batchSize);
    }

    public function setup()
    {
        $this->totalSteps = $this->indexer->getChunkCount();
        $this->currentStep = 0;
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
        if($this->indexer->finished()) {
            $this->isComplete = true;
            return;
        }
        $this->currentStep++;
        $this->indexer->tick();
    }

}
