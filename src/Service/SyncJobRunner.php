<?php

namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use Symbiote\QueuedJobs\Services\QueuedJob;

class SyncJobRunner
{

    use Injectable;

    public function runJob(QueuedJob $job, bool $verbose = true, int $level = 0): void
    {
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
    }

}
