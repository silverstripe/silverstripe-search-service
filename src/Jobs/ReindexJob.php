<?php

namespace SilverStripe\SearchService\Jobs;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use InvalidArgumentException;

/**
 * @property int|null $batchSize
 * @property DocumentFetcherInterface[]|null $fetchers
 * @property int|null $fetchIndex
 * @property int|null $fetchOffset
 * @property array|null $onlyClasses
 * @property array|null $onlyIndexes
 */
class ReindexJob extends AbstractQueuedJob implements QueuedJob
{
    use Injectable;
    use ConfigurationAware;
    use Extensible;

    /**
     * @var array
     */
    private static $dependencies = [
        'Registry' => '%$' . DocumentFetchCreatorRegistry::class,
        'Configuration' => '%$' . IndexConfiguration::class,
    ];

    /**
     * @var DocumentFetchCreatorRegistry
     */
    private $registry;

    /**
     * @param array|null $onlyClasses
     * @param array|null $onlyIndexes
     * @param int|null $batchSize
     */
    public function __construct(?array $onlyClasses = [], ?array $onlyIndexes = [], ?int $batchSize = null)
    {
        parent::__construct();

        $batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();

        $this->setOnlyClasses($onlyClasses);
        $this->setOnlyIndexes($onlyIndexes);
        $this->setBatchSize($batchSize);

        if ($this->getBatchSize() < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }
    }

    public function getTitle(): string
    {
        $title = 'Search service reindex all documents';

        if (!empty($this->getOnlyIndexes())) {
            $title .= ' in index ' . implode(',', $this->getOnlyIndexes());
        }

        if (!empty($this->getOnlyClasses())) {
            $title .= ' of class ' . implode(',', $this->getOnlyClasses());
        }

        return $title;
    }

    /**
     * @return int
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function setup()
    {
        Versioned::set_stage(Versioned::LIVE);

        if ($this->getOnlyIndexes() && count($this->getOnlyIndexes())) {
            $this->getConfiguration()->setOnlyIndexes($this->getOnlyIndexes());
        }

        $classes = $this->getOnlyClasses() && count($this->getOnlyClasses()) ?
            $this->getOnlyClasses() :
            $this->getConfiguration()->getSearchableBaseClasses();

        /* @var DocumentFetcherInterface[] $fetchers */
        $fetchers = [];

        foreach ($classes as $class) {
            $fetcher = $this->getRegistry()->getFetcher($class);

            if ($fetcher) {
                $fetchers[$class] = $fetcher;
            }
        }

        $steps = array_reduce($fetchers, function ($total, $fetcher) {
            /* @var DocumentFetcherInterface $fetcher */
            return $total + ceil($fetcher->getTotalDocuments() / $this->getBatchSize());
        }, 0);

        $this->totalSteps = $steps;
        $this->isComplete = $steps === 0;
        $this->currentStep = 0;
        $this->setFetchers(array_values($fetchers));
        $this->setFetchIndex(0);
        $this->setFetchOffset(0);
    }

    /**
     * Lets process a single node
     */
    public function process()
    {
        $this->extend('onBeforeProcess');
        $fetchers = $this->getFetchers();
        /* @var DocumentFetcherInterface $fetcher */
        $fetcher = $fetchers[$this->getFetchIndex()] ?? null;

        if (!$fetcher) {
            $this->isComplete = true;
            return;
        }

        $documents = $fetcher->fetch($this->getBatchSize(), $this->getFetchOffset());

        $indexer = Indexer::create($documents, Indexer::METHOD_ADD, $this->getBatchSize());
        $indexer->setProcessDependencies(false);

        while (!$indexer->finished()) {
            $indexer->processNode();
        }

        $nextOffset = $this->getFetchOffset() + $this->getBatchSize();

        if ($nextOffset >= $fetcher->getTotalDocuments()) {
            $this->incrementFetchIndex();
            $this->setFetchOffset(0);
        } else {
            $this->setFetchOffset($nextOffset);
        }

        $this->currentStep++;

        $this->extend('onAfterProcess');
    }

    public function getBatchSize(): ?int
    {
        if (is_bool($this->batchSize)) {
            return null;
        }

        return $this->batchSize;
    }

    public function getFetchers(): ?array
    {
        if (is_bool($this->fetchers)) {
            return null;
        }

        return $this->fetchers;
    }

    public function getFetchIndex(): ?int
    {
        if (is_bool($this->fetchIndex)) {
            return null;
        }

        return $this->fetchIndex;
    }

    public function getFetchOffset(): ?int
    {
        if (is_bool($this->fetchOffset)) {
            return null;
        }

        return $this->fetchOffset;
    }

    public function getOnlyClasses(): ?array
    {
        if (is_bool($this->onlyClasses)) {
            return null;
        }

        return $this->onlyClasses;
    }

    public function getOnlyIndexes(): ?array
    {
        if (is_bool($this->onlyIndexes)) {
            return null;
        }

        return $this->onlyIndexes;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    private function setFetchers(?array $fetchers): void
    {
        $this->fetchers = $fetchers;
    }

    private function setFetchIndex(?int $fetchIndex): void
    {
        $this->fetchIndex = $fetchIndex;
    }

    private function incrementFetchIndex(): void
    {
        $this->fetchIndex++;
    }

    private function setFetchOffset(?int $fetchOffset): void
    {
        $this->fetchOffset = $fetchOffset;
    }

    private function setOnlyClasses(?array $onlyClasses): void
    {
        $this->onlyClasses = $onlyClasses;
    }

    private function setOnlyIndexes(?array $onlyIndexes): void
    {
        $this->onlyIndexes = $onlyIndexes;
    }

    public function getRegistry(): DocumentFetchCreatorRegistry
    {
        return $this->registry;
    }

    public function setRegistry(DocumentFetchCreatorRegistry $registry): ReindexJob
    {
        $this->registry = $registry;

        return $this;
    }
}
