<?php


namespace SilverStripe\SearchService\Tests\Jobs;

use SilverStripe\SearchService\Jobs\ClearIndexJob;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;

require_once(__DIR__ . '/../SearchServiceTest.php');


class ClearIndexJobTest extends SearchServiceTest
{
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    public function testSetup()
    {
        $config = $this->mockConfig();
        $config->set('crawl_page_content', false);
        $this->loadIndex(20);
        $job = ClearIndexJob::create('myindex', 6);
        $job->setup();
        $this->assertEquals(4, $job->getJobData()->totalSteps);
        $this->assertFalse($job->jobFinished());
    }

    public function testProcess()
    {
        $config = $this->mockConfig();
        $config->set('crawl_page_content', false);
        $service = $this->loadIndex(20);
        $job = ClearIndexJob::create('myindex', 6);
        $job->setup();

        $job->process();
        $this->assertFalse($job->jobFinished());
        $this->assertEquals(14, $service->getDocumentTotal('myindex'));

        $job->process();
        $this->assertFalse($job->jobFinished());
        $this->assertEquals(8, $service->getDocumentTotal('myindex'));

        $job->process();
        $this->assertFalse($job->jobFinished());
        $this->assertEquals(2, $service->getDocumentTotal('myindex'));

        $job->process();
        $this->assertTrue($job->jobFinished());
        $this->assertEquals(0, $service->getDocumentTotal('myindex'));
    }
}
