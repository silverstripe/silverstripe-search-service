<?php


namespace SilverStripe\SearchService\Jobs;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Exception\IndexingServiceException;
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
     * @var int
     */
    private $totalCount = 0;

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
        $this->totalCount = $this->getIndexService()->getDocumentTotal($this->indexName);
        $this->totalSteps = ceil($this->totalCount / $this->batchSize);
        $this->isComplete = $this->totalCount === 0;
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
        $docs = $this->getIndexService()->listDocuments(
            $this->indexName,
            $this->batchSize
        );
        if (!empty($docs)) {
            $this->getIndexService()->removeDocuments($docs);
        }
        $total = $this->getIndexService()->getDocumentTotal($this->indexName);
        if ($total === 0) {
            $this->isComplete = true;
            return;
        }
        $this->currentStep++;

        if ($this->currentStep > $this->totalSteps) {
            throw new RuntimeException(sprintf(
                'ClearIndexJob was unable to delete all documents. Finished all steps and document total is still %s',
                $total
            ));
        }
    }
}
