<?php


namespace SilverStripe\SearchService\Tests\Fake;


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;

class ImageFake extends DataObject implements TestOnly
{
    private static $db = [
        'URL' => 'Varchar',
    ];

    private static $has_one = [
        'Parent' => DataObjectFake::class,
    ];

    private static $many_many = [
        'Tags' => TagFake::class,
    ];

    private static $extensions = [
        SearchServiceExtension::class,
    ];

}
