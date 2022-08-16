<?php

namespace SilverStripe\SearchService\Tests\DataObject;

use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\DataObject\DataObjectFetcher;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class DataObjectFetcherTest extends SearchServiceTest
{

    protected static $fixture_file = '../fixtures.yml'; // phpcs:ignore

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    public function testFetch(): void
    {
        $fetcher = DataObjectFetcher::create(DataObjectFake::class);
        /** @var DataObjectDocument[] $documents */
        $documents = $fetcher->fetch();

        // Quick sanity check to make sure we have the correct number of documents
        $this->assertCount(3, $documents);

        // Now start building out a basic $expectedDocuments, which is just going to be a combination of the ClassName
        // and Title
        $expectedDocuments = [
            sprintf('%s-Dataobject one', DataObjectFake::class),
            sprintf('%s-Dataobject two', DataObjectFake::class),
            sprintf('%s-Dataobject three', DataObjectFake::class),
        ];

        $resultDocuments = [];

        foreach ($documents as $document) {
            // We expect each of our Documents to be a DataObjectDocument specifically
            $this->assertInstanceOf(DataObjectDocument::class, $document);

            // And now let's start collating our ClassName/Title data
            $resultDocuments[] = sprintf('%s-%s', $document->getSourceClass(), $document->getDataObject()?->Title);
        }

        $this->assertEqualsCanonicalizing($expectedDocuments, $resultDocuments);

        // And then just a quick extra sanity check that fetching 2 Documents only returns 2 Documents
        $documents = $fetcher->fetch(2);

        $this->assertCount(2, $documents);
    }

    public function testTotalDocuments(): void
    {
        $fetcher = DataObjectFetcher::create(DataObjectFake::class);
        $this->assertEquals(3, $fetcher->getTotalDocuments());
    }

    public function testCreateDocument(): void
    {
        $dataobject = $this->objFromFixture(DataObjectFake::class, 'one');
        $id = $dataobject->ID;

        $fetcher = DataObjectFetcher::create(DataObjectFake::class);

        $this->expectException('InvalidArgumentException');
        $fetcher->createDocument(['title' => 'foo']);

        /** @var DataObjectDocument $doc */
        $doc = $fetcher->createDocument(['record_id' => $id, 'title' => 'foo']);
        $this->assertNotNull($doc);
        $this->assertEquals(DataObjectFake::class, $doc->getSourceClass());
        $this->assertEquals($id, $doc->getDataObject()->ID);

        $doc = $fetcher->createDocument(['record_id' => 0]);
        $this->assertNull($doc);
    }

}
