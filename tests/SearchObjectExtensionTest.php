<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use Wilr\SilverStripe\Algolia\Service\SearchService;

class SearchObjectExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        SearchTestObject::class
    ];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // mock SearchService
        Injector::inst()->get(DataObjectSchema::class)->reset();
        Injector::inst()->registerService(new TestSearchService(), SearchService::class);
    }

    public function testIndexInAlgolia()
    {
        $object = SearchTestObject::create();
        $object->Active = false;
        $object->write();

        $this->assertFalse($object->indexInAlgolia(), 'Objects with canIndexInAlgolia() set to false should not index');

        $object->Active = true;
        $object->write();

        $this->assertTrue($object->indexInAlgolia(), 'Objects with canIndexInAlgolia() set to true should index');
    }

    public function testTouchAlgoliaIndexedDate()
    {
        $object = SearchTestObject::create();
        $object->write();

        $object->touchAlgoliaIndexedDate();

        $this->assertNotNull(DB::query(sprintf(
            'SELECT AlgoliaIndexed FROM SearchTestObject WHERE ID = %s',
            $object->ID
        )));
    }
}
