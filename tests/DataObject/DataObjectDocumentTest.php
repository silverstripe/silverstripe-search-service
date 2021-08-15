<?php

namespace SilverStripe\SearchService\Tests\DataObject;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\RelationList;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Interfaces\DocumentAddHandler;
use SilverStripe\SearchService\Interfaces\DocumentRemoveHandler;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\DataObjectFakeVersioned;
use SilverStripe\SearchService\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\SearchService\Tests\Fake\ImageFake;
use SilverStripe\SearchService\Tests\Fake\TagFake;
use SilverStripe\SearchService\Tests\Fake\VersionedDataObjectFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\Versioned\Versioned;

class DataObjectDocumentTest extends SearchServiceTest
{
    protected static $fixture_file = '../fixtures.yml';

    protected static $extra_dataobjects = [
        VersionedDataObjectFake::class,
        DataObjectFake::class,
        TagFake::class,
        ImageFake::class,
        DataObjectSubclassFake::class,
        DataObjectFakeVersioned::class,
    ];

    public function testGetIdentifier()
    {
        $dataobject = new DataObjectFake(['ID' => 5]);
        $doc = DataObjectDocument::create($dataobject);
        $this->assertEquals('silverstripe_searchservice_tests_fake_dataobjectfake_5', $doc->getIdentifier());
    }

    public function testGetSourceClass()
    {
        $dataobject = new DataObjectFake(['ID' => 5]);
        $doc = DataObjectDocument::create($dataobject);
        $this->assertEquals(DataObjectFake::class, $doc->getSourceClass());
    }

    public function testShouldIndex()
    {
        $config = $this->mockConfig();
        /** @var Versioned $dataobject */
        $dataobject = new VersionedDataObjectFake(['ID' => 5, 'ShowInSearch' => true]);
        $dataobject->publishSingle();
        $doc = DataObjectDocument::create($dataobject);

        $config->set('getIndexesForDocument', [
            $doc->getIdentifier() => [
                'index' => 'data'
            ]
        ]);

        $dataobject->can_view = false;
        $this->assertFalse($doc->shouldIndex());
        $dataobject->can_view = function () {
            return Permission::check('ADMIN');
        };
        $this->assertFalse($doc->shouldIndex());
        $dataobject->can_view = true;
        $this->assertTrue($doc->shouldIndex());

        $dataobject->ShowInSearch = false;
        $this->assertFalse($doc->shouldIndex());
        $dataobject->ShowInSearch = true;
        $this->assertTrue($doc->shouldIndex());

        $dataobject->doUnpublish();
        $this->assertFalse($doc->shouldIndex());
        $dataobject->publishSingle();
        $this->assertTrue($doc->shouldIndex());

        $config->set('enabled', false);
        $this->assertFalse($doc->shouldIndex());
    }

    public function testMarkIndexed()
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

    public function testGetIndexes()
    {
        $config = $this->mockConfig();
        $config->set('getIndexesForClassName', [DataObjectFake::class => ['one', 'two']]);

        $dataobject = new DataObjectFake(['ShowInSearch' => true]);
        $doc = DataObjectDocument::create($dataobject);
        $this->assertEquals(['one', 'two'], $doc->getIndexes());
    }

    public function testToArray()
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
            ]
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
            ]
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
//        $this->expectExceptionMessageRegExp('/associative/');
//        $doc->toArray();
//
//        $this->expectException(IndexConfigurationException::class);
//        $this->expectExceptionMessageRegExp('/non scalar/');
//        $config->set('getFieldsForClass', [
//            DataObjectFake::class => [
//                new Field('customgettermixed', 'CustomGetterMixedArray'),
//            ]
//        ]);
//        $doc->toArray();

        $this->expectException(IndexConfigurationException::class);
        $this->expectExceptionMessageRegExp('/DataObject or RelationList/');
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('tags', 'Tags'),
            ]
        ]);
        $doc->toArray();

        $this->expectException(IndexConfigurationException::class);
        $this->expectExceptionMessageRegExp('/DataObject or RelationList/');
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('customgetterdataobject', 'CustomGetterDataObj'),
            ]
        ]);
        $doc->toArray();

        $this->expectException(IndexConfigurationException::class);
        $this->expectExceptionMessageRegExp('/cannot be resolved/');
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('customgetterobj', 'CustomGetterObj'),
            ]
        ]);
        $doc->toArray();
    }

    public function testProvideMeta()
    {
        $dataObject = $this->objFromFixture(DataObjectFake::class, 'one');
        $doc = DataObjectDocument::create($dataObject);
        $meta = $doc->provideMeta();
        $this->assertArrayHasKey('record_base_class', $meta);
        $this->assertArrayHasKey('record_id', $meta);
        $this->assertEquals(DataObjectFake::class, $meta['record_base_class']);
        $this->assertEquals($dataObject->ID, $meta['record_id']);
    }

    public function testGetIndexedFields()
    {
        $config = $this->mockConfig();
        $doc = DataObjectDocument::create(new DataObjectSubclassFake());
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('title'),
            ]
        ]);

        $fields = $doc->getIndexedFields();
        $this->assertCount(1, $fields);
        $this->assertEquals('title', $fields[0]->getSearchFieldName());

        $config->set('getFieldsForClass', [
            DataObjectSubclassFake::class => [
                new Field('title'),
            ]
        ]);

        $fields = $doc->getIndexedFields();
        $this->assertCount(1, $fields);
        $this->assertEquals('title', $fields[0]->getSearchFieldName());

        $doc->setDataObject(new DataObjectFake());
        $fields = $doc->getIndexedFields();
        $this->assertEmpty($fields);
    }

    public function testGetFieldDependency()
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

    public function testGetFieldValue()
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

    public function testGetSearchValueNoHTML()
    {
        $config = $this->mockConfig();
        $config->set('getFieldsForClass', [
            DataObjectFake::class => [
                new Field('htmltext', 'getDBHTMLText'),
                new Field('htmlstring', 'getHTMLString'),
                new Field('multi', 'getAMultiLineString'),
            ]
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

    public function testGetDependentDocuments()
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
            ]
        ]);

        $dataobject = $this->objFromFixture(TagFake::class, 'one');
        $doc = DataObjectDocument::create($dataobject);
        $dependencies = $doc->getDependentDocuments();

        $this->assertCount(4, $dependencies);


        $this->assertArrayContainsCallback($dependencies, function (DataObjectDocument $item) {
            $obj = $this->objFromFixture(DataObjectFake::class, 'one');
            return $item->getSourceClass() === DataObjectFake::class &&
                $item->getDataObject()->ID === $obj->ID;
        });
        $this->assertArrayContainsCallback($dependencies, function (DataObjectDocument $item) {
            $obj = $this->objFromFixture(DataObjectFake::class, 'two');
            return $item->getSourceClass() === DataObjectFake::class &&
                $item->getDataObject()->ID === $obj->ID;
        });
        $this->assertArrayContainsCallback($dependencies, function (DataObjectDocument $item) {
            $obj = $this->objFromFixture(DataObjectFake::class, 'three');
            return $item->getSourceClass() === DataObjectFake::class &&
                $item->getDataObject()->ID === $obj->ID;
        });
        $this->assertArrayContainsCallback($dependencies, function (DataObjectDocument $item) {
            $obj = $this->objFromFixture(ImageFake::class, 'two');
            return $item->getSourceClass() === ImageFake::class &&
                $item->getDataObject()->ID === $obj->ID;
        });
    }

    public function testExtensionRequired()
    {
        $this->expectException('InvalidArgumentException');
        $doc = DataObjectDocument::create(new Member());

        $fake = DataObjectFake::create();
        $doc->setDataObject($fake);

        $this->assertEquals($fake, $doc->getDataObject());
    }

    public function testEvents()
    {
        $mock = $this->getMockBuilder(DataObjectDocument::class)
            ->setMethods(['markIndexed'])
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->exactly(2))
            ->method('markIndexed');

        $mock->onAddToSearchIndexes(DocumentAddHandler::BEFORE_ADD);
        $mock->onAddToSearchIndexes(DocumentAddHandler::AFTER_ADD);
        $mock->onRemoveFromSearchIndexes(DocumentRemoveHandler::BEFORE_REMOVE);
        $mock->onRemoveFromSearchIndexes(DocumentRemoveHandler::AFTER_REMOVE);
    }

    public function testDeletedDataObject()
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
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            sprintf("DataObject %s : %s does not exist", DataObjectFakeVersioned::class, $id)
        );

        unserialize(serialize($doc));
    }
}
