<?php

namespace SilverStripe\SearchService\Tests\DataObject;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SearchService\DataObject\DataObjectBatchProcessor;
use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Jobs\RemoveDataObjectJob;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Tests\Fake\DataObjectDocumentFake;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class DataObjectBatchProcessorTest extends SearchServiceTest
{
    public function testRemoveDocuments(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);

        Config::modify()->set(
            DataObjectBatchProcessor::class,
            'buffer_seconds',
            100
        );
        DBDatetime::set_mock_now(1000);

        $syncRunnerMock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();
        $cb = function (RemoveDataObjectJob $arg) {
            $this->assertInstanceOf(RemoveDataObjectJob::class, $arg);
            $this->assertCount(1, $arg->indexer->getDocuments());
            $this->assertEquals(Indexer::METHOD_ADD, $arg->indexer->getMethod());
            $this->assertEquals(900, $arg->timestamp);
        };

        $syncRunnerMock->expects($this->exactly(3))
            ->method('runJob')
            ->withConsecutive(
                $this->callback(function (IndexJob $arg) {
                    $this->assertInstanceOf(IndexJob::class, $arg);
                    $this->assertCount(2, $arg->indexer->getDocuments());
                    $this->assertEquals(Indexer::METHOD_DELETE, $arg->indexer->getMethod());
                }),
                $this->callback($cb),
                $this->callback($cb)
            );

        Injector::inst()->registerService($syncRunnerMock, SyncJobRunner::class);

        $processor = new DataObjectBatchProcessor(IndexConfiguration::singleton());

        $processor->removeDocuments(
            [
                new DataObjectDocumentFake(new DataObjectFake()),
                new DataObjectDocumentFake(new DataObjectFake()),
            ]
        );
    }
}
