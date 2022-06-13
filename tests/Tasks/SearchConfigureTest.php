<?php

namespace SilverStripe\SearchService\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Tasks\SearchClearIndex;
use SilverStripe\SearchService\Tasks\SearchConfigure;
use SilverStripe\SearchService\Tests\Fake\ServiceFake;

class SearchConfigureTest extends SapphireTest
{
    public function testTask(): void
    {
        $mock = $this->getMockBuilder(ServiceFake::class)
            ->setMethods(['configure'])
            ->getMock();
        $mock->expects($this->once())
            ->method('configure');
        Injector::inst()->registerService($mock, IndexingInterface::class);

        $task = SearchConfigure::create();
        $request = new HTTPRequest('GET', '/', []);

        $task->run($request);
    }
}
