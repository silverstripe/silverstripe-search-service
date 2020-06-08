<?php


namespace SilverStripe\SearchService\Jobs;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
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
     * @param string $indexName
     * @param int|null $batchSize
     */
    public function __construct(string $indexName, ?int $batchSize = null)
    {
        parent::__construct();
        $this->indexName = $indexName;
        $this->batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();
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
        $docs = $this->getIndexService()->listDocuments($this->indexName, $this->batchSize);
        $ids = array_map(function (DocumentInterface $doc) {
            return $doc->getIdentifier();
        }, $docs);
        $this->getIndexService()->removeDocuments($ids);
        $this->currentStep++;
    }
}
