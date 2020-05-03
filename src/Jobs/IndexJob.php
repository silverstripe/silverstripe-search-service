<?php

namespace SilverStripe\SearchService\Jobs;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\ServiceAware;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Index an item (or multiple items) into search async. This method works well
 * for performance and batching large indexes
 */
class IndexJob extends AbstractQueuedJob implements QueuedJob
{
    use Injectable;
    use ServiceAware;

    /**
     * @var array
     */
    private static $dependencies = [
        'SearchService' => '%$' . SearchServiceInterface::class,
    ];

    /**
     * @var DocumentInterface[]
     */
    private $documents = [];

    /**
     * @var DocumentInterface[]
     */
    private $chunks = [];

    /**
     * @var SearchServiceInterface
     */
    private $service;

    /**
     * @param DocumentInterface[] $documents
     * @paramn int $batchSize
     */
    public function __construct(array $documents = [], int $batchSize = 20)
    {
        $this->documents = $documents;
        $this->chunks = array_chunk($documents, $batchSize);
        $this->totalSteps = sizeof($this->chunks);
    }

    /**
     * Defines the title of the job.
     *
     * @return string
     */
    public function getTitle()
    {
        return sprintf(
            'Search service reindex %s documents in %s chunks',
            sizeof($this->documents),
            sizeof($this->chunks)
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
     * This is called immediately before a job begins - it gives you a chance
     * to initialise job data and make sure everything's good to go
     *
     * What we're doing in our case is to queue up the list of items we know we need to
     * process still (it's not everything - just the ones we know at the moment)
     *
     * When we go through, we'll constantly add and remove from this queue, meaning
     * we never overload it with content
     */
    public function setup()
    {
        $this->isComplete = !count($this->documents);
    }

    /**
     * Lets process a single node
     */
    public function process()
    {
        $remainingChildren = $this->chunks;

        if (!count($remainingChildren)) {
            $this->isComplete = true;

            return;
        }

        $this->currentStep++;
        $documents = array_shift($remainingChildren);
        $this->getSearchService()->addDocuments($documents);
        $this->chunks = $remainingChildren;

        if (!count($remainingChildren)) {
            $this->isComplete = true;
            return;
        }
    }

}
