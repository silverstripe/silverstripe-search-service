<?php


namespace SilverStripe\SearchService\Tests\Service;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Service\BatchProcessor;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\SearchService\Service\SyncJobRunner;
use SilverStripe\SearchService\Tests\Fake\DocumentFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class BatchProcessorTest extends SearchServiceTest
{
    public function testAddDocumentsSync(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);

        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->setMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->indexer->getDocuments()) === 2 &&
                    $job->indexer->getMethod() === Indexer::METHOD_ADD;
            }));
        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $processor = new BatchProcessor($config);
        $processor->addDocuments([
            new DocumentFake('Fake', ['test' => 'foo']),
            new DocumentFake('Fake', ['test' => 'bar'])
        ]);
    }

    public function testRemoveDocumentsSync(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);

        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->setMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->indexer->getDocuments()) === 2 &&
                    $job->indexer->getMethod() === Indexer::METHOD_DELETE;
            }));
        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $processor = new BatchProcessor($config);
        $processor->removeDocuments([
            new DocumentFake('Fake', ['test' => 'foo']),
            new DocumentFake('Fake', ['test' => 'bar'])
        ]);
    }

    public function testAddDocumentsQueued(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', false);

        $mock = $this->getMockBuilder(QueuedJobService::class)
            ->setMethods(['queueJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('queueJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->indexer->getDocuments()) === 2 &&
                    $job->indexer->getMethod() === Indexer::METHOD_ADD;
            }));

        Injector::inst()->registerService($mock, QueuedJobService::class);

        $processor = new BatchProcessor($config);
        $processor->addDocuments([
            new DocumentFake('Fake', ['test' => 'foo']),
            new DocumentFake('Fake', ['test' => 'bar'])
        ]);
    }

    public function testRemoveDocumentsQueued(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', false);

        $mock = $this->getMockBuilder(QueuedJobService::class)
            ->setMethods(['queueJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('queueJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->indexer->getDocuments()) === 2 &&
                    $job->indexer->getMethod() === Indexer::METHOD_DELETE;
            }));

        Injector::inst()->registerService($mock, QueuedJobService::class);

        $processor = new BatchProcessor($config);
        $processor->removeDocuments([
            new DocumentFake('Fake', ['test' => 'foo']),
            new DocumentFake('Fake', ['test' => 'bar'])
        ]);
    }
}
