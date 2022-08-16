<?php

namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;

/**
 * @property string $Title
 * @mixin SearchServiceExtension
 */
class DataObjectFakeAlternate extends DataObject implements TestOnly
{

    private static string $table_name = 'DataObjectFakeAlternate';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

}
