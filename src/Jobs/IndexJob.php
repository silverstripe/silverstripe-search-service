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
 * @property Indexer $indexer
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
        parent::__construct();
        $this->indexer = Indexer::create($documents, $method, $batchSize);
        $this->indexer->setProcessDependencies($processDependencies);
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
            $this->indexer->getMethod() === Indexer::METHOD_DELETE ? 'removing' : 'adding',
            sizeof($this->indexer->getDocuments())
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
        $this->extend('onBeforeProcess');
        $this->currentStep++;
        $this->indexer->processNode();
        $this->extend('onAfterProcess');

        if ($this->indexer->finished()) {
            $this->isComplete = true;
        }
    }
}
