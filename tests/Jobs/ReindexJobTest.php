<?php

namespace SilverStripe\SearchService\Tests\Jobs;

use SilverStripe\SearchService\DataObject\DataObjectFetcher;
use SilverStripe\SearchService\Jobs\ReindexJob;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\FakeFetchCreator;
use SilverStripe\SearchService\Tests\Fake\FakeFetcher;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class ReindexJobTest extends SearchServiceTest
{

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        FakeFetcher::load(10);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        FakeFetcher::$records = [];
    }

    public function testJob(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', [
            DataObjectFake::class => true,
            'Fake' => true,
        ]);
        $this->loadIndex(20);
        $registry = DocumentFetchCreatorRegistry::singleton();
        // Add a second fetcher to complicate things
        $registry->addFetchCreator(new FakeFetchCreator());

        $job = ReindexJob::create([DataObjectFake::class, 'Fake'], [], 6);

        $job->setup();
        $totalSteps = $job->getJobData()->totalSteps;
        // 20 dataobjectfake in batches of six = 4
        // 10 Fake documents in batches of six = 2
        $this->assertEquals(6, $totalSteps);

        $fetchers = $job->getFetchers();

        // Quick sanity check to make sure we got both fetchers
        $this->assertCount(2, $fetchers);

        // We're expecting one of each of these Fetcher classes
        $expectedFetchers = [
            DataObjectFetcher::class,
            FakeFetcher::class,
        ];

        $resultFetchers = [];

        foreach ($fetchers as $fetcher) {
            $resultFetchers[] = $fetcher::class;
        }

        $this->assertEqualsCanonicalizing($expectedFetchers, $resultFetchers);
    }

}
