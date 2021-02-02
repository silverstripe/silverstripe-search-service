<?php

namespace SilverStripe\SearchService\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Jobs\ReindexJob;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Tasks\SearchClearIndex;
use SilverStripe\SearchService\Tasks\SearchReindex;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class SearchReindexTest extends SearchServiceTest
{
    public function testTask()
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);
        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->setMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (ReindexJob $job) {
                return count($job->onlyClasses) === 1 && $job->onlyClasses[0] === 'foo';
            }));

        $task = SearchReindex::create();
        $request = new HTTPRequest('GET', '/', ['onlyClass' => 'foo']);

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($request);

        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->setMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (ReindexJob $job) {
                return empty($job->onlyClasses);
            }));

        $task = SearchReindex::create();
        $request = new HTTPRequest('GET', '/', []);

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($request);
    }
}
