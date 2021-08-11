<?php


namespace SilverStripe\SearchService\Tests\Jobs;

use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\DataObject\DataObjectFetcher;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Jobs\ReindexJob;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\FakeFetchCreator;
use SilverStripe\SearchService\Tests\Fake\FakeFetcher;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class ReindexJobTest extends SearchServiceTest
{
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        FakeFetcher::load(10);
    }

    protected function tearDown()
    {
        parent::tearDown();
        FakeFetcher::$records = [];
    }

    public function testJob()
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', [
            DataObjectFake::class => true,
            'Fake' => true,
        ]);
        $service = $this->loadIndex(20);
        $registry = DocumentFetchCreatorRegistry::singleton();
        // Add a second fetcher to complicate things
        $registry->addFetchCreator(new FakeFetchCreator());

        $job = ReindexJob::create([DataObjectFake::class, 'Fake'], [], 6);

        $job->setup();
        $totalSteps = $job->getJobData()->totalSteps;
        // 20 dataobjectfake in batches of six = 4
        // 10 Fake documents in batches of six = 2
        $this->assertEquals(6, $totalSteps);

        $this->assertCount(2, $job->fetchers);
        $this->assertArrayContainsCallback($job->fetchers, function (DocumentFetcherInterface $fetcher) {
            return $fetcher instanceof DataObjectFetcher;
        });
        $this->assertArrayContainsCallback($job->fetchers, function (DocumentFetcherInterface $fetcher) {
            return $fetcher instanceof FakeFetcher;
        });
    }
}
