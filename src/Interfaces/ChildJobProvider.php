<?php


namespace SilverStripe\SearchService\Interfaces;


use Symbiote\QueuedJobs\Services\QueuedJob;

interface ChildJobProvider
{
    /**
     * @return QueuedJob[]
     */
    public function getChildJobs(): array;

    /**
     * @param bool $bool
     * @return $this
     */
    public function setDeferChildJobs(bool $bool);
}
