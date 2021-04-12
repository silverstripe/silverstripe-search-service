<?php


namespace SilverStripe\SearchService\Tests\Jobs;

use InvalidArgumentException;
use RuntimeException;
use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class ClearIndexJobTest extends SearchServiceTest
{
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    public function testConstruct()
    {
        $config = $this->mockConfig();

        // Batch size of 0 is the same as not specifying a batch size, so we should get the batch size from config
        $job = ClearIndexJob::create('myindex', 0);
        $this->assertSame($config->getBatchSize(), $job->batchSize);

        // Same with not specifying a batch size at all
        $job = ClearIndexJob::create('myindex');
        $this->assertSame($config->getBatchSize(), $job->batchSize);

        // Specifying a batch size under 0 should throw an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch size must be greater than 0');
        $job = ClearIndexJob::create('myindex', -1);

        // If no index name is provided, then other config options should not be applied
        $job = ClearIndexJob::create();
        $this->assertNull($job->indexName);
        $this->assertNull($job->batchSize);
        $this->assertNull($job->batchOffset);
    }

    public function testSetup()
    {
        $config = $this->mockConfig();
        $config->set('crawl_page_content', false);
        $this->loadIndex(10);
        $job = ClearIndexJob::create('myindex', 1);
        $job->setup();

        // Total number of steps should always be 5 no matter the size of the index or batch size
        $this->assertEquals(5, $job->getJobData()->totalSteps);
        $this->assertFalse($job->jobFinished());
    }

    public function testGetTitle()
    {
        $job = ClearIndexJob::create('indexName');
        $this->assertContains('indexName', $job->getTitle());

        $job = ClearIndexJob::create('random_index_name');
        $this->assertContains('random_index_name', $job->getTitle());
    }

    public function testProcess()
    {
        $config = $this->mockConfig();
        $config->set('crawl_page_content', false);
        $service = $this->loadIndex(20);
        $job = ClearIndexJob::create('myindex', 5);
        $job->setup();

        $job->process();
        $this->assertTrue($job->jobFinished());
        $this->assertEquals(0, $service->getDocumentTotal('myindex'));

        // Now create a fake test where we don't remove any documents
        $service = $this->loadIndex(10);
        $service->shouldError = true;
        $job = ClearIndexJob::create('myindex', 5);
        $job->setup();

        // We try to run up to 5 times before failing - the first 5 runs should process but not do anything...
        $job->process();
        $job->process();
        $job->process();
        $job->process();
        $job->process();

        // The 6th time we process should fail with a RuntimeException
        $msg = 'ClearIndexJob was unable to delete all documents after 5 attempts. Finished all steps and the document'
            .   ' total is still 10';
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($msg);
        $job->process();
    }
}
