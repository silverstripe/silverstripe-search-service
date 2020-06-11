<?php


namespace SilverStripe\SearchService\Jobs;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\ServiceAware;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

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
     * @param string $indexName
     * @param int|null $batchSize
     */
    public function __construct(string $indexName, ?int $batchSize = null)
    {
        parent::__construct();
        $this->indexName = $indexName;
        $this->batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();
        $this->batchOffset = 0;
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
            $this->batchSize,
            $this->batchOffset
        );
        if (!empty($docs)) {
            $this->getIndexService()->removeDocuments($docs);
        }
        $this->batchOffset += $this->batchSize;
        if ($this->batchOffset > $this->totalCount) {
            $this->isComplete = true;
        }
        $this->currentStep++;
    }
}
