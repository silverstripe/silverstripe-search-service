<?php


namespace SilverStripe\SearchService\Interfaces;


use Symbiote\QueuedJobs\Services\QueuedJob;

interface ChildJobProvider
{
    /**
     * @return QueuedJob[]
     */
    public function getChildJobs(): array;
}
