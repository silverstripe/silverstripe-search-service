<?php


namespace SilverStripe\SearchService\Tests\Service;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\SearchService\DataObject\DataObjectFetchCreator;
use SilverStripe\SearchService\DataObject\DataObjectFetcher;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\FakeFetchCreator;
use SilverStripe\SearchService\Tests\Fake\FakeFetcher;

class DocumentFetchCreatorRegistryTest extends SapphireTest
{
    public function testRegistry()
    {
        $registry = new DocumentFetchCreatorRegistry(
            $fake = new FakeFetchCreator(),
            $dataobject = new DataObjectFetchCreator()
        );

        $fetcher = $registry->getFetcher('Fake');
        $this->assertNotNull($fetcher);
        $this->assertInstanceOf(FakeFetcher::class, $fetcher);

        $fetcher = $registry->getFetcher(DataObjectFake::class);
        $this->assertNotNull($fetcher);
        $this->assertInstanceOf(DataObjectFetcher::class, $fetcher);

        $registry->removeFetchCreator($dataobject);

        $fetcher = $registry->getFetcher(DataObjectFake::class);
        $this->assertNull($fetcher);

        $registry->removeFetchCreator($fake);
        $fetcher = $registry->getFetcher('Fake');
        $this->assertNull($fetcher);
    }
}
