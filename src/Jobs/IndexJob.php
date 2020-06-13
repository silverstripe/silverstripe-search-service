<?php

namespace SilverStripe\SearchService\Jobs;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SearchService\Interfaces\DependencyTracker;
use SilverStripe\SearchService\Interfaces\DocumentAddHandler;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentRemoveHandler;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\ConfigurationAware;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\ServiceAware;
use Symbiote\QueuedJobs\Services\QueuedJob;
use InvalidArgumentException;

/**
 * Index an item (or multiple items) into search async. This method works well
 * for performance and batching large indexes
 *
 * @property DocumentInterface[] $documents
 * @property string $method
 * @property int $batchSize
 * @property bool $processDependencies
 * @property DocumentInterface $referrerDocument
 */
class IndexJob extends AbstractChildJobProvider implements QueuedJob
{
    use Injectable;
    use ServiceAware;
    use ConfigurationAware;

    const METHOD_DELETE = 0;

    const METHOD_ADD = 1;

    /**
     * @var array
     */
    private static $dependencies = [
        'IndexService' => '%$' . IndexingInterface::class,
        'Configuration' => '%$' . IndexConfiguration::class,
    ];

    /**
     * @var DocumentInterface[]
     */
    private $chunks = [];

    /**
     * @var IndexingInterface
     */
    private $service;

    /**
     * @param DocumentInterface[] $documents
     * @param int $method
     * @param int $batchSize
     */
    public function __construct(
        array $documents = [],
        int $method = self::METHOD_ADD,
        ?int $batchSize = null
    ) {
        parent::__construct();
        $this->documents = $documents;
        $this->batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();
        $this->processDependencies = true;
        $this->setMethod($method);
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
            $this->method === self::METHOD_DELETE ? 'removing' : 'adding',
            sizeof($this->documents)
        );
    }

    /**
     * @return int
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
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
        $this->chunks = array_chunk($this->documents, $this->batchSize);
        $this->totalSteps = sizeof($this->chunks);
        $this->isComplete = !count($this->documents);
    }

    /**
     * Lets process a single node
     * @throws ValidationException
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
        if ($this->method === static::METHOD_DELETE) {
            $this->getIndexService()->removeDocuments($documents);
        } else {
            $toRemove = [];
            $toUpdate = [];
            /* @var DocumentInterface $document */
            foreach ($documents as $document) {
                if (!$this->getConfiguration()->isClassIndexed($document->getSourceClass())) {
                    continue;
                }
                if ($document->shouldIndex()) {
                    if ($document instanceof DocumentAddHandler) {
                        $document->onAddToSearchIndexes(DocumentAddHandler::BEFORE_ADD);
                    }
                    $toUpdate[] = $document;
                } else {
                    if ($document instanceof DocumentRemoveHandler) {
                        $document->onRemoveFromSearchIndexes(DocumentRemoveHandler::BEFORE_REMOVE);
                    }
                    $toRemove[] = $document;
                }
            }
            if (!empty($toUpdate)) {
                $this->getIndexService()->addDocuments($toUpdate);
                foreach ($toUpdate as $document) {
                    if ($document instanceof DocumentAddHandler) {
                        $document->onAddToSearchIndexes(DocumentAddHandler::AFTER_ADD);
                    }
                }
            }
            if (!empty($toRemove)) {
                $this->getIndexService()->removeDocuments($toRemove);
                foreach ($toRemove as $document) {
                    if ($document instanceof DocumentRemoveHandler) {
                        $document->onRemoveFromSearchIndexes(DocumentRemoveHandler::AFTER_REMOVE);
                    }
                }
            }
        }

        $this->chunks = $remainingChildren;

        if ($this->processDependencies) {
            foreach ($documents as $document) {
                if ($document instanceof DependencyTracker) {
                    $dependentDocs = array_filter(
                        $document->getDependentDocuments(),
                        function (DocumentInterface $dependentDocument) use ($document) {
                            return $dependentDocument->getIdentifier() !== $document->getIdentifier();
                        }
                    );
                    if (!empty($dependentDocs)) {
                        $childJob = IndexJob::create($dependentDocs);
                        $this->runChildJob($childJob);
                    }
                }
            }
        }

        if (!count($remainingChildren)) {
            $this->isComplete = true;
            return;
        }
    }

    /**
     * @param $method
     * @return $this
     */
    public function setMethod($method): self
    {
        if (!in_array($method, [self::METHOD_ADD, self::METHOD_DELETE])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid method: %s',
                $method
            ));
        }

        $this->__set('method', $method);

        return $this;
    }

    /**
     * @param bool $processDependencies
     * @return IndexJob
     */
    public function setProcessDependencies(bool $processDependencies): IndexJob
    {
        $this->processDependencies = $processDependencies;
        return $this;
    }

    /**
     * @param int $batchSize
     * @return IndexJob
     */
    public function setBatchSize(int $batchSize): IndexJob
    {
        $this->batchSize = $batchSize;
        return $this;
    }

}
