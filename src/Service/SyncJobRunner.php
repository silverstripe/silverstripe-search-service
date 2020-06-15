<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\ChildJobProvider;
use Symbiote\QueuedJobs\Services\QueuedJob;

class SyncJobRunner
{
    use Injectable;

    /**
     * @param QueuedJob $job
     * @param bool $verbose
     * @param int $level
     */
    public function runJob(QueuedJob $job, bool $verbose = true, $level = 0)
    {
        if ($job instanceof ChildJobProvider) {
            $job->setDeferChildJobs(true);
        }

        if ($verbose) {
            echo sprintf(
                '%sRunning%sjob %s%s',
                str_repeat('  ', $level),
                $level > 0 ? ' child ' : '',
                $job->getTitle(),
                PHP_EOL
            );
        }
        $job->setup();
        while (!$job->jobFinished()) {
            $job->process();
        }
        if ($job instanceof ChildJobProvider) {
            $childJobs = $job->getChildJobs();
            if (!empty($childJobs)) {
                if ($verbose) {
                    echo sprintf(
                        '%s%s child jobs created%s',
                        str_repeat('  ', $level),
                        count($childJobs),
                        PHP_EOL
                    );
                }
                foreach ($job->getChildJobs() as $childJob) {
                    $this->runJob($childJob, $verbose, $level + 1);
                }
            }
        }
    }
}
