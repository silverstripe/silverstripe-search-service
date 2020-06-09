<?php


namespace SilverStripe\SearchService\Jobs;


use SilverStripe\ORM\ValidationException;
use SilverStripe\SearchService\Interfaces\ChildJobProvider;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

abstract class AbstractChildJobProvider extends AbstractQueuedJob implements ChildJobProvider
{
    /**
     * @var QueuedJob[]
     */
    private $childJobs = [];

    /**
     * @var bool
     */
    private $deferChildJobs = false;

    /**
     * @return QueuedJob[]
     */
    public function getChildJobs(): array
    {
        return $this->childJobs;
    }

    /**
     * @param QueuedJob $job
     * @throws ValidationException
     */
    protected function runChildJob(QueuedJob $job)
    {
        if ($this->deferChildJobs) {
            $this->childJobs[] = $job;
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }
    }

    /**
     * @param bool $bool
     * @return $this
     */
    public function setDeferChildJobs(bool $bool): self
    {
        $this->deferChildJobs = $bool;

        return $this;
    }
}
