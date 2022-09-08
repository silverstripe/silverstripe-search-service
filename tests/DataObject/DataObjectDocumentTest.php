<?php

namespace SilverStripe\SearchService\Tests\DataObject;

use Page;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\RelationList;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Interfaces\DocumentAddHandler;
use SilverStripe\SearchService\Interfaces\DocumentRemoveHandler;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\DataObjectFakePrivate;
use SilverStripe\SearchService\Tests\Fake\DataObjectFakeVersioned;
use SilverStripe\SearchService\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\SearchService\Tests\Fake\ImageFake;
use SilverStripe\SearchService\Tests\Fake\TagFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;
use SilverStripe\Security\Member;

class DataObjectDocumentTest extends SearchServiceTest
{

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $fixture_file = [
        '../fixtures.yml',
        '../pages.yml',
    ];

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
        TagFake::class,
        ImageFake::class,
        DataObjectSubclassFake::class,
        DataObjectFakeVersioned::class,
        DataObjectFakePrivate::class,
    ];

    public function testGetIdentifier(): void
    {
        $dataobject = new DataObjectFake(['ID' => 5]);
        $doc = DataObjectDocument::create($dataobject);
        $this->assertEquals('silverstripe_searchservice_tests_fake_dataobjectfake_5', $doc->getIdentifier());
    }

    public function testGetSourceClass(): void
    {
        $dataobject = new DataObjectFake(['ID' => 5]);
        $doc = DataObjectDocument::create($dataobject);
        $this->assertEquals(DataObjectFake::class, $doc->getSourceClass());
    }

    public function testShouldIndex(): void
    {
        $config = $this->mockConfig();

        $dataObjectOne = $this->objFromFixture(DataObjectFakeVersioned::class, 'one');
        $dataObjectTwo = $this->objFromFixture(DataObjectFakeVersioned::class, 'two');
        $dataObjectThree = $this->objFromFixture(DataObjectFakePrivate::class, 'one');

        // DocOne and Two represent DOs that have not yet been published
        $docOne = DataObjectDocument::create($dataObjectOne);
        $docTwo = DataObjectDocument::create($dataObjectTwo);
        $docThree = DataObjectDocument::create($dataObjectThree);

        // Add all three documents to our indexes, as this isn't the functionality we're testing here
        $config->set(
            'getIndexesForDocument',
            [
                $docOne->getIdentifier() => [
                    'index' => 'data',
                ],
                $docTwo->getIdentifier() => [
                    'index' => 'data',
                ],
                $docThree->getIdentifier() => [
                    'index' => 'data',
                ],
            ]
        );

        // All should be unavailable to index initially
        $this->assertFalse($docOne->shouldIndex());
        $this->assertFalse($docTwo->shouldIndex());
        $this->assertFalse($docThree->shouldIndex());

        // Make sure both Versioned DOs are now published
        $dataObjectOne->publishRecursive();
        $dataObjectTwo->publishRecursive();

        // Need to re-fetch these DOs as their Versioned data will have changes
        $dataObjectOne = $this->objFromFixture(DataObjectFakeVersioned::class, 'one');
        $dataObjectTwo = $this->objFromFixture(DataObjectFakeVersioned::class, 'two');

        // Recreate the Documents as well
        $docOne = DataObjectDocument::create($dataObjectOne);
        $docTwo = DataObjectDocument::create($dataObjectTwo);

        // Document one should be indexable (as it's published and has ShowInSearch: 1)
        $this->assertTrue($docOne->shouldIndex());
        // Document two should NOT be indexable (it's published but has ShowInSearch: 0)
        $this->assertFalse($docTwo->shouldIndex());
        // Document three should NOT be indexable (canView(): false)
        $this->assertFalse($docThree->shouldIndex());
    }

    public function testShouldIndexChild(): void
    {
        $config = $this->mockConfig();

        $parent = $this->objFromFixture(Page::class, 'page1');
        // Make sure our Parent is published before we fetch our child pages
        $parent->publishRecursive();

        $childOne = $this->objFromFixture(Page::class, 'page2');
        $childTwo = $this->objFromFixture(Page::class, 'page3');
        $childThree = $this->objFromFixture(Page::class, 'page5');

        // Publish childOne and childThree
        $childOne->publishRecursive();
        $childThree->publishRecursive();
        // Need to re-fetch childOne and childThree so that our Versioned state is up-to-date with what we just
        // published
        $childOne = $this->objFromFixture(Page::class, 'page2');
        $childThree = $this->objFromFixture(Page::class, 'page5');

        // $docOne has a published page
        $docOne = DataObjectDocument::create($childOne);
        // $docTwo has an unpublished page
        $docTwo = DataObjectDocument::create($childTwo);
        // $docThree has a published page
        $docThree = DataObjectDocument::create($childThree);

        // Add both documents to our indexes, as this isn't the functionality we're testing here
        $config->set(
            'getIndexesForDocument',
            [
                $docOne->getIdentifier() => [
                    'index' => 'data',
                ],
                $docTwo->getIdentifier() => [
                    'index' => 'data',
                ],
                $docThree->getIdentifier() => [
                    'index' => 'data',
                ],
            ]
        );

        // Our parent page has been published, and so has our child page, so this should be indexable
        $this->assertTrue($docOne->shouldIndex());
        // Our parent page has been published, but the child has not
        $this->assertFalse($docTwo->shouldIndex());
        // Our parent page is not published, even though the child is
        $this->assertFalse($docThree->shouldIndex());

        // Now trigger a change on our parent page (so that the draft and live versions no longer match)
        $parent->Title = 'Parent Page Changed';
        $parent->write();

        // Need to re-fetch childOne so that we re-fetch the Parent when we request canView()
        $childOne = $this->objFromFixture(Page::class, 'page2');
        // Recreate the Document with our new child page
        $docOne = DataObjectDocument::create($childOne);
        // Check that our child page is still indexable, even after our parent page was given a different draft version
        $this->assertTrue($docOne->shouldIndex());
    }

    public function testMarkIndexed(): void
    {
        $dataobject = new DataObjectFake(['ShowInSearch' => true]);
        $dataobject->write();
        $doc = DataObjectDocument::create($dataobject);
        DBDatetime::set_mock_now('2020-01-29 12:05:00');
        $doc->markIndexed();
        $result = DataObject::get_by_id(DataObjectFake::class, $dataobject->ID);
        $this->assertNotNull($result);
        $this->assertEquals('2020-01-29 12:05:00', $result->SearchIndexed);

        $doc->markIndexed(true);
        $result = DataObject::get_by_id(DataObjectFake::class, $dataobject->ID, false);
        $this->assertNotNull($result);
        $this->assertNull($result->SearchIndexed);
    }

    public function testGetIndexes(): void
    {
        $config = $this->mockConfig();
        $config->set('getIndexesForClassName', [DataObjectFake::class => ['one', 'two']]);

        $dataobject = new DataObjectFake(['ShowInSearch' => true]);
        $doc = DataObjectDocument::create($dataobject);
        $this->assertEquals(['one', 'two'], $doc->getIndexes());
    }

    public function testToArray(): void
    {
        $config = $this->mockConfig();
        $config->set('crawl_page_content', false);

        $dataObject = $this->objFromFixture(DataObjectFake::class, 'one');
        $doc = DataObjectDocument::create($dataObject);
        // Avoid testing this case for now, as it needs to be refactored

        // happy path
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('title'),
                new Field('memberfirst', 'Member.FirstName'),
                new Field('tagtitles', 'Tags.Title'),
                new Field('imageurls', 'Images.URL'),
                new Field('imagetags', 'Images.Tags.Title'),
            ],
        ]);

        $arr = $doc->toArray();

        $this->assertArrayHasKey('title', $arr);
        $this->assertArrayHasKey('memberfirst', $arr);
        $this->assertArrayHasKey('tagtitles', $arr);
        $this->assertArrayHasKey('imageurls', $arr);
        $this->assertArrayHasKey('imagetags', $arr);

        $this->assertEquals('Dataobject one', $arr['title']);
        $this->assertEquals('member-one-first', $arr['memberfirst']);
        $this->assertEquals(['Tag one', 'Tag two'], $arr['tagtitles']);
        $this->assertEquals(['/image-one/'], $arr['imageurls']);
        $this->assertEquals(['Tag two', 'Tag three'], $arr['imagetags']);

        // non existent fields
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('noexist'),
                new Field('title', 'Nothing'),
            ],
        ]);

        $arr = $doc->toArray();

        $this->assertArrayHasKey('noexist', $arr);
        $this->assertEmpty($arr['noexist']);
        $this->assertArrayHasKey('title', $arr);
        $this->assertEmpty($arr['title']);

        // Currently toArray() uses obj() only, so it's not possible to return an array.
        // Should support a config for the field that allows getting the uncasted value
        // of a method, e.g. getMyArray(): array, so it isn't coerced into a DBField.

//        // exceptions
//        $config->set('getFieldsForClass', [
//            DataObjectFake::class => [
//                new Field('customgettermap', 'CustomGetterMap'),
//            ]
//        ]);
//        $this->expectException(IndexConfigurationException::class);
//        $this->expectExceptionMessageMatches('/associative/');
//        $doc->toArray();
//
//        $this->expectException(IndexConfigurationException::class);
//        $this->expectExceptionMessageMatches('/non scalar/');
//        $config->set('getFieldsForClass', [
//            DataObjectFake::class => [
//                new Field('customgettermixed', 'CustomGetterMixedArray'),
//            ]
//        ]);
//        $doc->toArray();

        $this->expectException(IndexConfigurationException::class);
        $this->expectExceptionMessageMatches('/DataObject or RelationList/');
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('tags', 'Tags'),
            ],
        ]);
        $doc->toArray();

        $this->expectException(IndexConfigurationException::class);
        $this->expectExceptionMessageMatches('/DataObject or RelationList/');
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('customgetterdataobject', 'CustomGetterDataObj'),
            ],
        ]);
        $doc->toArray();

        $this->expectException(IndexConfigurationException::class);
        $this->expectExceptionMessageMatches('/cannot be resolved/');
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('customgetterobj', 'CustomGetterObj'),
            ],
        ]);
        $doc->toArray();
    }

    public function testProvideMeta(): void
    {
        $dataObject = $this->objFromFixture(DataObjectFake::class, 'one');
        $doc = DataObjectDocument::create($dataObject);
        $meta = $doc->provideMeta();

        $this->assertArrayHasKey('record_base_class', $meta);
        $this->assertArrayHasKey('record_id', $meta);
        $this->assertEquals(DataObjectFake::class, $meta['record_base_class']);
        $this->assertEquals($dataObject->ID, $meta['record_id']);
    }

    public function testGetIndexedFields(): void
    {
        $config = $this->mockConfig();
        $doc = DataObjectDocument::create(new DataObjectSubclassFake());
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('title'),
            ],
        ]);

        $fields = $doc->getIndexedFields();
        $this->assertCount(1, $fields);
        $this->assertEquals('title', $fields[0]->getSearchFieldName());

        $config->set('getFieldsForClass', [
            DataObjectSubclassFake::class => [
                new Field('title'),
            ],
        ]);

        $fields = $doc->getIndexedFields();
        $this->assertCount(1, $fields);
        $this->assertEquals('title', $fields[0]->getSearchFieldName());

        $doc->setDataObject(new DataObjectFake());
        $fields = $doc->getIndexedFields();
        $this->assertEmpty($fields);
    }

    public function testGetFieldDependency(): void
    {
        $dataObject = $this->objFromFixture(DataObjectFake::class, 'one');
        $doc = DataObjectDocument::create($dataObject);
        $dependency = $doc->getFieldDependency(new Field('memberfirst', 'Member.FirstName'));
        $this->assertNotNull($dependency);
        $this->assertInstanceOf(Member::class, $dependency);
        $member = $this->objFromFixture(Member::class, 'one');
        $this->assertEquals($member->ID, $dependency->ID);

        $dependency = $doc->getFieldDependency(new Field('title'));
        $this->assertNull($dependency);

        $dependency = $doc->getFieldDependency(new Field('imagetags', 'Images.Tags.Title'));
        $this->assertInstanceOf(RelationList::class, $dependency);
        $this->assertCount(2, $dependency);
        $this->assertEquals(['Tag two', 'Tag three'], $dependency->column('Title'));
    }

    public function testGetFieldValue(): void
    {
        $dataObject = $this->objFromFixture(DataObjectFake::class, 'one');
        $doc = DataObjectDocument::create($dataObject);

        $value = $doc->getFieldValue(new Field('title'));
        $this->assertEquals('Dataobject one', $value);

        $value = $doc->getFieldValue(new Field('memberfirst', 'Member.FirstName'));
        $this->assertNotNull($value);
        $this->assertEquals('member-one-first', $value);


        $value = $doc->getFieldValue(new Field('imagetags', 'Images.Tags.Title'));
        $this->assertCount(2, $value);
        $this->assertEquals(['Tag two', 'Tag three'], $value);
    }

    public function testGetSearchValueNoHTML(): void
    {
        $config = $this->mockConfig();
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('htmltext', 'getDBHTMLText'),
                new Field('htmlstring', 'getHTMLString'),
                new Field('multi', 'getAMultiLineString'),
            ],
        ]);
        $config->set('include_page_html', true);
        $dataObject = $this->objFromFixture(DataObjectFake::class, 'one');
        $doc = DataObjectDocument::create($dataObject);

        $array = $doc->toArray();
        $this->assertArrayHasKey('htmltext', $array);
        $this->assertArrayHasKey('htmlstring', $array);
        $this->assertArrayHasKey('multi', $array);
        $this->assertEquals(
            "<h1>WHAT ARE WE YELLING ABOUT?</h1> Then a break <br />Then a new line\nand a tab\t",
            $array['htmltext']
        );
        $this->assertEquals(
            'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
            $array['htmlstring']
        );
        $this->assertEquals('a multi line string', $array['multi']);

        $config->set('include_page_html', false);
        $doc = DataObjectDocument::create($dataObject);
        $array = $doc->toArray();
        $this->assertArrayHasKey('htmltext', $array);
        $this->assertArrayHasKey('htmlstring', $array);
        $this->assertArrayHasKey('multi', $array);
        $this->assertEquals(
            'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
            $array['htmltext']
        );
        $this->assertEquals(
            'WHAT ARE WE YELLING ABOUT? Then a break Then a new line and a tab ',
            $array['htmlstring']
        );
        $this->assertEquals('a multi line string', $array['multi']);
    }

    public function testGetDependentDocuments(): void
    {
        $config = $this->mockConfig();
        $config->set('getSearchableClasses', [
            DataObjectFake::class,
            ImageFake::class,
        ]);
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('title'),
                new Field('memberfirst', 'Member.FirstName'),
                new Field('tagtitles', 'Tags.Title'),
                new Field('imageurls', 'Images.URL'),
                new Field('imagetags', 'Images.Tags.Title'),
            ],
            ImageFake::class => [
                new Field('tagtitles', 'Tags.Title'),
            ],
        ]);

        $dataobject = $this->objFromFixture(TagFake::class, 'one');
        $doc = DataObjectDocument::create($dataobject);
        /** @var DataObjectDocument[] $dependentDocuments */
        $dependentDocuments = $doc->getDependentDocuments();

        // Quick check to make sure there is the correct number of dependent Documents
        $this->assertCount(4, $dependentDocuments);

        // Grab all the expected DataObjects
        $dataObjectOne = $this->objFromFixture(DataObjectFake::class, 'one');
        $dataObjectTwo = $this->objFromFixture(DataObjectFake::class, 'two');
        $dataObjectThree = $this->objFromFixture(DataObjectFake::class, 'three');
        $dataObjectFour = $this->objFromFixture(ImageFake::class, 'two');

        // Now start building out a basic $expectedDocuments, which is just going to be a combination of the ClassName
        // and ID
        $expectedDocuments = [
            sprintf('%s-%s', DataObjectFake::class, $dataObjectOne->ID),
            sprintf('%s-%s', DataObjectFake::class, $dataObjectTwo->ID),
            sprintf('%s-%s', DataObjectFake::class, $dataObjectThree->ID),
            sprintf('%s-%s', ImageFake::class, $dataObjectFour->ID),
        ];

        $resultDocuments = [];

        // Now let's check that each Document represents the DataObjects that we expected (above)
        foreach ($dependentDocuments as $document) {
            $resultDocuments[] = sprintf('%s-%s', $document->getSourceClass(), $document->getDataObject()?->ID);
        }

        $this->assertEqualsCanonicalizing($expectedDocuments, $resultDocuments);
    }

    public function testExtensionRequired(): void
    {
        $this->expectException('InvalidArgumentException');
        $doc = DataObjectDocument::create(new Member());

        $fake = DataObjectFake::create();
        $doc->setDataObject($fake);

        $this->assertEquals($fake, $doc->getDataObject());
    }

    public function testEvents(): void
    {
        $mock = $this->getMockBuilder(DataObjectDocument::class)
            ->onlyMethods(['markIndexed'])
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->exactly(2))
            ->method('markIndexed');

        $mock->onAddToSearchIndexes(DocumentAddHandler::BEFORE_ADD);
        $mock->onAddToSearchIndexes(DocumentAddHandler::AFTER_ADD);
        $mock->onRemoveFromSearchIndexes(DocumentRemoveHandler::BEFORE_REMOVE);
        $mock->onRemoveFromSearchIndexes(DocumentRemoveHandler::AFTER_REMOVE);
    }

    public function testDeletedDataObject(): void
    {
        $dataObject = $this->objFromFixture(DataObjectFakeVersioned::class, 'one');
        $dataObject->Title = 'Published';
        $dataObject->publishRecursive();
        $id = $dataObject->ID;

        $doc = DataObjectDocument::create($dataObject)->setShouldFallbackToLatestVersion(true);
        $dataObject->delete();

        /** @var DataObjectDocument $serialDoc */
        $serialDoc = unserialize(serialize($doc));
        $this->assertEquals($id, $serialDoc->getDataObject()->ID);

        $doc->setShouldFallbackToLatestVersion(false);
        $this->expectExceptionMessage(
            sprintf('DataObject %s : %s does not exist', DataObjectFakeVersioned::class, $id)
        );

        unserialize(serialize($doc));
    }

}
