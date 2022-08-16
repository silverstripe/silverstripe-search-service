<?php

namespace SilverStripe\SearchService\Jobs;

use Exception;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Indexer;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Index an item (or multiple items) into search async. This method works well for performance and batching large
 * indexes
 *
 * @property DocumentInterface[] $documents
 * @property DocumentInterface[] $remainingDocuments
 * @property int $method
 * @property int|null $batchSize
 * @property bool $processDependencies
 */
class IndexJob extends AbstractQueuedJob implements QueuedJob
{

    use Injectable;
    use Extensible;

    /**
     * @param DocumentInterface[] $documents
     */
    public function __construct(
        array $documents = [],
        int $method = Indexer::METHOD_ADD,
        ?int $batchSize = null,
        bool $processDependencies = true
    ) {
        $batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();

        $this->setDocuments($documents);
        $this->setMethod($method);
        $this->setBatchSize($batchSize);
        $this->setProcessDependencies($processDependencies);

        parent::__construct();
    }

    public function setup(): void
    {
        if (!$this->getBatchSize()) {
            // If we don't have a batchSize, then we're just processing everything in one go
            $this->totalSteps = 1;
        } else {
            // There could be 0 documents. If that's the case, then there's zero steps
            $this->totalSteps = $this->getDocuments()
                ? ceil(count($this->getDocuments()) / $this->getBatchSize())
                : 0;
        }

        $this->currentStep = 0;
        $this->setRemainingDocuments($this->getDocuments());

        parent::setup();
    }

    public function getTitle(): string
    {
        return sprintf(
            'Search service %s %s documents',
            $this->getMethod() === Indexer::METHOD_DELETE ? 'removing' : 'adding',
            sizeof($this->getDocuments())
        );
    }

    public function getJobType(): int
    {
        return QueuedJob::IMMEDIATE;
    }

    public function process(): void
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
        if (!is_array($this->documents)) {
            return [];
        }

        return $this->documents;
    }

    public function getRemainingDocuments(): array
    {
        if (!is_array($this->remainingDocuments)) {
            return [];
        }

        return $this->remainingDocuments;
    }

    public function getMethod(): int
    {
        if (!is_int($this->method)) {
            // Performing the wrong method here could be disastrous, so we'd rather break
            throw new Exception('No method provided for IndexJob');
        }

        return $this->method;
    }

    public function getBatchSize(): ?int
    {
        if (is_bool($this->batchSize)) {
            return null;
        }

        return $this->batchSize;
    }

    public function shouldProcessDependencies(): bool
    {
        if (!is_bool($this->processDependencies)) {
            // Default is to process dependencies, and it doesn't really hurt for us to do so
            return true;
        }

        return $this->processDependencies;
    }

    protected function setDocuments(array $documents): void
    {
        $this->documents = $documents;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
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
