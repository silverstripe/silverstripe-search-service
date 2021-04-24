<?php


namespace SilverStripe\SearchService\Jobs;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Traits\ServiceAware;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use RuntimeException;
use InvalidArgumentException;

/**
 * Class ClearIndexJob
 * @package SilverStripe\SearchService\Jobs
 *
 * @property string $indexName
 * @property int $batchSize
 * @property int $batchOffset
 */
class ClearIndexJob extends AbstractQueuedJob implements QueuedJob
{
    use Injectable;
    use ServiceAware;

    private static $dependencies = [
        'IndexService' => '%$' . IndexingInterface::class,
    ];

    /**
     * ClearIndexJob constructor.
     * @param string|null $indexName
     * @param int|null $batchSize
     */
    public function __construct(?string $indexName = null, ?int $batchSize = null)
    {
        parent::__construct();

        if (!$indexName) {
            return;
        }

        $this->indexName = $indexName;
        $this->batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();
        $this->batchOffset = 0;

        if ($this->batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }
    }

    /**
     * @throws IndexingServiceException
     */
    public function setup()
    {
        // Attempt to remove all documents up to 5 times to allow for eventually-consistent data stores
        $this->totalSteps = 5;
    }

    public function getTitle()
    {
        return sprintf('Search clear index %s', $this->indexName);
    }

    /**
     * @throws IndexingServiceException
     */
    public function process()
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
        $total = $this->getIndexService()->getDocumentTotal($this->indexName);
        $numRemoved = $this->getIndexService()->removeAllDocuments($this->indexName);
        $totalAfter = $this->getIndexService()->getDocumentTotal($this->indexName);

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
                $this->indexName
            ));

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
}
