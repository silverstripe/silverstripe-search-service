<?php

namespace SilverStripe\SearchService\Tests\DataObject;

require_once(__DIR__ . '/../SearchServiceTest.php');

use SilverStripe\Control\Controller;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\DataObject\DataObjectFetcher;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class DataObjectFetcherTest extends SearchServiceTest
{
    protected static $fixture_file = '../fixtures.yml';

    protected static $extra_dataobjects = [
        DataObjectFake::class
    ];

    public function testConstructor()
    {
        $this->expectException('InvalidArgumentException');
        DataObjectFetcher::create(Controller::class);
    }

    public function testFetch()
    {
        $fetcher = DataObjectFetcher::create(DataObjectFake::class);
        $result = $fetcher->fetch();
        $this->assertCount(3, $result);
        foreach (['Dataobject one', 'Dataobject two', 'Dataobject three'] as $title) {
            $this->assertArrayContainsCallback($result, function (DataObjectDocument $doc) use ($title) {
                return $doc instanceof DataObjectDocument &&
                    $doc->getSourceClass() === DataObjectFake::class &&
                    $doc->getDataObject()->Title === $title;
            });
        }

        $result = $fetcher->fetch(2);
        $this->assertCount(2, $result);
    }

    public function testTotalDocuments()
    {
        $fetcher = DataObjectFetcher::create(DataObjectFake::class);
        $this->assertEquals(3, $fetcher->getTotalDocuments());
    }

    public function testCreateDocument()
    {
        $dataobject = $this->objFromFixture(DataObjectFake::class, 'one');
        $id = $dataobject->ID;

        $fetcher = DataObjectFetcher::create(DataObjectFake::class);

        $this->expectException('InvalidArgumentException');
        $fetcher->createDocument(['title' => 'foo']);

        /* @var DataObjectDocument $doc */
        $doc = $fetcher->createDocument(['record_id' => $id, 'title' => 'foo']);
        $this->assertNotNull($doc);
        $this->assertEquals(DataObjectFake::class, $doc->getSourceClass());
        $this->assertEquals($id, $doc->getDataObject()->ID);

        $doc = $fetcher->createDocument(['record_id' => 0]);
        $this->assertNull($doc);
    }
}
