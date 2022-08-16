<?php

namespace SilverStripe\SearchService\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Jobs\ReindexJob;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Tasks\SearchReindex;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class SearchReindexTest extends SearchServiceTest
{

    public function testTask(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);
        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (ReindexJob $job) {
                return count($job->getOnlyClasses()) === 1 && $job->getOnlyClasses()[0] === 'foo';
            }));

        $task = SearchReindex::create();
        $request = new HTTPRequest('GET', '/', ['onlyClass' => 'foo']);

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($request);

        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (ReindexJob $job) {
                return !$job->getOnlyClasses();
            }));

        $task = SearchReindex::create();
        $request = new HTTPRequest('GET', '/', []);

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($request);
    }

}
