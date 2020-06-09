<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\ChildJobProvider;
use Symbiote\QueuedJobs\Services\QueuedJob;

class SyncJobRunner
{
    use Injectable;

    public function runJob(QueuedJob $job, $level = 0)
    {
        echo sprintf(
            '%sRunning%sjob %s%s',
            str_repeat('  ', $level),
            $level > 0 ? ' child ' : '',
            $job->getTitle(),
            PHP_EOL
        );

        $job->setup();
        while(!$job->jobFinished()) {
            $job->process();
        }
        if ($job instanceof ChildJobProvider) {
            $childJobs = $job->getChildJobs();
            if (!empty($childJobs)) {
                echo sprintf(
                    '%s%s child jobs created%s',
                    str_repeat('  ', $level),
                    count($childJobs),
                    PHP_EOL
                );
                foreach ($job->getChildJobs() as $childJob) {
                    $this->runJob($childJob, $level + 1);
                }
            }
        }
    }
}
