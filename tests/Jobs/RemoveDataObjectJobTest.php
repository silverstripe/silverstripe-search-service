<?php


namespace SilverStripe\SearchService\Tests\Jobs;

use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Jobs\RemoveDataObjectJob;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\ImageFake;
use SilverStripe\SearchService\Tests\Fake\TagFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;
use SilverStripe\Security\Member;

class RemoveDataObjectJobTest extends SearchServiceTest
{
    protected static $fixture_file = '../fixtures.yml';

    protected static $extra_dataobjects = [
        DataObjectFake::class,
        TagFake::class,
        ImageFake::class,
        Member::class,
    ];

    public function testJob()
    {
        $config = $this->mockConfig();
        $service = $this->mockService();

        $config->set('getSearchableClasses', [
            DataObjectFake::class,
            TagFake::class,
        ]);

        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('title'),
                new Field('tagtitles', 'Tags.Title'),
            ]
        ]);

        $dataobject = $this->objFromFixture(DataObjectFake::class, 'one');
        $service->addDocument($doc = DataObjectDocument::create($dataobject));
        $this->assertCount(1, $service->listDocuments('test'));
        $doc = $service->documents[$doc->getIdentifier()] ?? null;
        $this->assertNotNull($doc);
        $this->assertArrayHasKey('tagtitles', $doc);
        $this->assertCount(2, $doc['tagtitles']);

        // delete a tag
        $tag = $dataobject->Tags()->first();
        $job = RemoveDataObjectJob::create(
            DataObjectDocument::create($tag)
        );
        $job->setup();
        $docs = $job->indexer->getDocuments();
        $this->assertCount(2, $docs);
        foreach (['Dataobject one', 'Dataobject three'] as $title) {
            $this->assertArrayContainsCallback($docs, function (DataObjectDocument $doc) use ($title) {
                return $doc->getDataObject()->Title === $title;
            });
        }
    }
}
