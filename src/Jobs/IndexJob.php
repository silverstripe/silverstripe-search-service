<?php

namespace SilverStripe\SearchService\Jobs;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\Indexer;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Index an item (or multiple items) into search async. This method works well for performance and batching large
 * indexes
 */
class IndexJob extends AbstractQueuedJob implements QueuedJob
{
    use Injectable;
    use Extensible;

    /**
     * @var DocumentInterface[]
     */
    private array $documents;

    /**
     * @var DocumentInterface[]
     */
    private array $remainingDocuments;

    private int $method;

    private ?int $batchSize;

    private bool $processDependencies;

    /**
     * @param DocumentInterface[] $documents
     */
    public function __construct(
        array $documents = [],
        int $method = Indexer::METHOD_ADD,
        ?int $batchSize = null,
        bool $processDependencies = true
    ) {
        $this->setDocuments($documents);
        $this->setMethod($method);
        $this->setBatchSize($batchSize);
        $this->setProcessDependencies($processDependencies);

        parent::__construct();
    }

    public function setup()
    {
        if (!$this->getBatchSize()) {
            // If we don't have a batchSize, then we're just processing everything in one go
            $this->totalSteps = 1;
        } else {
            // There could be 0 documents. If that's the case, then there's zero steps
            $this->totalSteps = $this->documents
                ? ceil(count($this->documents) / $this->batchSize)
                : 0;
        }

        $this->currentStep = 0;
        $this->setRemainingDocuments($this->documents);

        parent::setup();
    }

    public function getTitle()
    {
        return sprintf(
            'Search service %s %s documents',
            $this->getMethod() === Indexer::METHOD_DELETE ? 'removing' : 'adding',
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

    public function process()
    {
        // It is possible that this Job is queued with no documents to be updated. If so, just mark it as complete
        if ($this->totalSteps === 0) {
            $this->isComplete = true;

            return;
        }

        $remainingDocuments = $this->getRemainingDocuments();
        // Splice a bunch of Documents from the start of the remaining documents
        $documentToProcess = array_splice($remainingDocuments, 0, $this->getBatchSize());

        // Indexer is being instantiated in process() rather that __construct() to prevent the following exception:
        // Uncaught Exception: Serialization of 'CurlHandle' is not allowed
        // The CurlHandle is created in a third-party dependency
        $indexer = Indexer::create($documentToProcess, $this->getMethod(), $this->getBatchSize());
        $indexer->setProcessDependencies($this->shouldProcessDependencies());

        $this->extend('onBeforeProcess');
        $indexer->processNode();
        $this->extend('onAfterProcess');

        // Save away whatever Documents are still remaining
        $this->setRemainingDocuments($remainingDocuments);
        $this->currentStep++;

        if ($this->currentStep >= $this->totalSteps) {
            $this->isComplete = true;
        }
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function getRemainingDocuments(): array
    {
        return $this->remainingDocuments;
    }

    public function getMethod(): int
    {
        return $this->method;
    }

    public function getBatchSize(): ?int
    {
        return $this->batchSize;
    }

    public function shouldProcessDependencies(): bool
    {
        return $this->processDependencies;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    private function setDocuments(array $documents): void
    {
        $this->documents = $documents;
    }

    private function setMethod(int $method): void
    {
        $this->method = $method;
    }

    private function setRemainingDocuments(array $remainingDocuments): void
    {
        $this->remainingDocuments = $remainingDocuments;
    }

    private function setProcessDependencies(bool $processDependencies): void
    {
        $this->processDependencies = $processDependencies;
    }
}
