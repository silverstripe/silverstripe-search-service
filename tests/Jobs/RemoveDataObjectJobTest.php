<?php

namespace SilverStripe\SearchService\Tests\Jobs;

use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Jobs\RemoveDataObjectJob;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\DataObjectFakePrivate;
use SilverStripe\SearchService\Tests\Fake\DataObjectFakeVersioned;
use SilverStripe\SearchService\Tests\Fake\ImageFake;
use SilverStripe\SearchService\Tests\Fake\TagFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;
use SilverStripe\Security\Member;

class RemoveDataObjectJobTest extends SearchServiceTest
{

    protected static $fixture_file = '../fixtures.yml'; // phpcs:ignore

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
        DataObjectFakePrivate::class,
        DataObjectFakeVersioned::class,
        TagFake::class,
        ImageFake::class,
        Member::class,
    ];

    public function testJob(): void
    {
        $config = $this->mockConfig();

        $config->set(
            'getSearchableClasses',
            [
                DataObjectFake::class,
                TagFake::class,
            ]
        );

        $config->set(
            'getFieldsForClass',
            [
                DataObjectFake::class => [
                    new Field('title'),
                    new Field('tagtitles', 'Tags.Title'),
                ],
            ]
        );

        // Select tag one from our fixture
        $tag = $this->objFromFixture(TagFake::class, 'one');
        // Queue up a job to remove our Tag, the result should be that any related DataObject (DOs that have this Tag
        // assigned to them) are added as related Documents
        $job = RemoveDataObjectJob::create(
            DataObjectDocument::create($tag)
        );
        $job->setup();

        // Grab what Documents the Job determined it needed to action
        /** @var DataObjectDocument[] $documents */
        $documents = $job->getDocuments();

        // There should be two Pages with this Tag assigned
        $this->assertCount(2, $documents);

        $expectedTitles = [
            'Dataobject one',
            'Dataobject three',
        ];

        $resultTitles = [];

        foreach ($documents as $document) {
            $resultTitles[] = $document->getDataObject()?->Title;
        }

        $this->assertEqualsCanonicalizing($expectedTitles, $resultTitles);
    }

}
