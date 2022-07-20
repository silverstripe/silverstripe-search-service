<?php


namespace SilverStripe\SearchService\Tests\Jobs;

use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class IndexJobTest extends SearchServiceTest
{
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    public function testJob(): void
    {
        $config = $this->mockConfig();
        $config->set('isClassIndexed', [
            DataObjectFake::class => true,
        ]);
        $config->set('batch_size', 6);
        $service = $this->loadIndex(20);
        $docs = $service->listDocuments('test', 100);
        $this->assertCount(20, $docs);

        $job = IndexJob::create($docs, Indexer::METHOD_ADD);
        $job->setup();
        $this->assertEquals(6, $job->getBatchSize());
        $this->assertCount(20, $job->getDocuments());

        $job->process();
        $this->assertFalse($job->jobFinished());
        $job->process();
        $this->assertFalse($job->jobFinished());
        $job->process();
        $this->assertFalse($job->jobFinished());
        $job->process();
        $this->assertTrue($job->jobFinished());
    }
}
