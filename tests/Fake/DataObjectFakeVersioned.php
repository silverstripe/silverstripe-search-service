<?php


namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\Versioned\Versioned;

class DataObjectFakeVersioned extends DataObject implements TestOnly
{
    private static $table_name = 'DataObjectFakeVersioned';

    private static $extensions = [
        SearchServiceExtension::class,
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];
}
