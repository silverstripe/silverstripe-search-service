<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObjectSchema;
use Wilr\SilverStripe\Algolia\Service\Indexer;
use Wilr\SilverStripe\Algolia\Service\SearchService;

class IndexerTest extends SapphireTest
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

    public function testExportAttributesForObject()
    {
        $object = SearchTestObject::create();
        $object->Title = 'Foobar';
        $object->write();
        $indexer = Injector::inst()->get(Indexer::class);
        $map = $indexer->exportAttributesFromObject($object)->toArray();

        $this->assertArrayHasKey('objectID', $map);
        $this->assertEquals($map['objectTitle'], 'Foobar');
    }
}
