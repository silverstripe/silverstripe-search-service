<?php

namespace SilverStripe\SearchService\Jobs;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\SearchService\Interfaces\IndexingDocumentTypeInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Service\Traits\ServiceAware;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * @property int|null $batchOffset
 * @property int|null $batchSize
 * @property string|null $indexName
 * @property array $documentTypes
 * @property bool $reindex
 */
class ClearIndexJob extends AbstractQueuedJob implements QueuedJob
{

    use Injectable;
    use ServiceAware;

    private static array $dependencies = [
        'IndexService' => '%$' . IndexingInterface::class,
    ];

    public function __construct(
        ?string $indexName = null,
        ?int $batchSize = null,
        string $types = '',
        bool $reindex = false
    ) {
        parent::__construct();

        if (!$indexName) {
            return;
        }

        $batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();

        $this->setIndexName($indexName);
        $this->setDocumentTypes($types);
        $this->setBatchSize($batchSize);
        $this->setBatchOffset(0);
        $this->setReindex($reindex);

        if (!$this->getBatchSize() || $this->getBatchSize() < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }
    }

    public function setup(): void
    {
        // Attempt to remove all documents up to 5 times to allow for eventually-consistent data stores
        $this->totalSteps = 5;
    }

    public function getTitle(): string
    {
        return sprintf('Search clear index %s', $this->getIndexName());
    }

    public function process(): void
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        if (!$this->getIndexService() instanceof BatchDocumentRemovalInterface) {
            Injector::inst()->get(LoggerInterface::class)->error(sprintf(
                'Index service "%s" does not implement the %s interface. Cannot remove all documents',
                get_class($this->getIndexService()),
                BatchDocumentRemovalInterface::class
            ));

            $this->isComplete = true;

            return;
        }

        $this->currentStep++;

        if ($this->getIndexService() instanceof IndexingDocumentTypeInterface) {
            $this->getIndexService()->setDocumentTypes($this->getDocumentTypes());
        }

        $total = $this->getIndexService()->getDocumentTotal($this->getIndexName());
        $numRemoved = $this->getIndexService()->removeAllDocuments($this->getIndexName());
        $totalAfter = $this->getIndexService()->getDocumentTotal($this->getIndexName());

        Injector::inst()->get(LoggerInterface::class)->notice(sprintf(
            '[Step %d]: Before there were %d documents. We removed %d documents this iteration, leaving %d remaining.',
            $this->currentStep,
            $total,
            $numRemoved,
            $totalAfter
        ));

        if ($totalAfter === 0) {
            $this->isComplete = true;
            Injector::inst()->get(LoggerInterface::class)->notice(sprintf(
                'Successfully removed all documents from index %s',
                $this->getIndexName()
            ));

            $this->scheduleReindex();

            return;
        }

        if ($this->currentStep > $this->totalSteps) {
            throw new RuntimeException(sprintf(
                'ClearIndexJob was unable to delete all documents after %d attempts. Finished all steps and the'
                    . ' document total is still %d',
                $this->totalSteps,
                $totalAfter
            ));
        }
    }

    public function getBatchOffset(): ?int
    {
        if (is_bool($this->batchOffset)) {
            return null;
        }

        return $this->batchOffset;
    }

    public function getBatchSize(): ?int
    {
        if (is_bool($this->batchSize)) {
            return null;
        }

        return $this->batchSize;
    }

    public function getIndexName(): ?string
    {
        if (is_bool($this->indexName)) {
            return null;
        }

        return $this->indexName;
    }

    public function getDocumentTypes(): array
    {
        return $this->documentTypes;
    }

    public function shouldReindex(): bool
    {
        return $this->reindex;
    }

    private function setBatchOffset(?int $batchOffset): void
    {
        $this->batchOffset = $batchOffset;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    private function setIndexName(?string $indexName): void
    {
        $this->indexName = $indexName;
    }

    private function setDocumentTypes(string $types): void
    {
        $this->documentTypes = $types !== '' ? array_map('trim', explode(',', $types)) : [];
    }

    private function setReindex(bool $reindex): void
    {
        $this->reindex = $reindex;
    }

    private function scheduleReindex(): void
    {
        // Skip if reindex not requested
        if (!$this->shouldReindex()) {
            return;
        }

        $targetClasses = $this->getDocumentTypes();

        // ReindexJob expects false (all class names) or collection of class names
        if (!count($targetClasses)) {
            $targetClasses = false;
        }

        $job = ReindexJob::create($targetClasses, $this->getIndexName());

        if (IndexConfiguration::singleton()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }
    }

}
