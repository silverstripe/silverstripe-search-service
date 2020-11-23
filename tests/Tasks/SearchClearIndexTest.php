<?php

namespace SilverStripe\SearchService\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Tasks\SearchClearIndex;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class SearchClearIndexTest extends SearchServiceTest
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
            ->with($this->callback(function (ClearIndexJob $job) {
                return $job->indexName === 'foo';
            }));

        $task = SearchClearIndex::create();
        $request = new HTTPRequest('GET', '/', ['index' => 'foo']);

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($request);

        $request = new HTTPRequest('GET', '/', []);

        $this->expectException('InvalidArgumentException');
        $task->run($request);
    }
}
