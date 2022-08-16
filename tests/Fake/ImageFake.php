<?php

namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;

class ImageFake extends DataObject implements TestOnly
{

    private static array $db = [
        'URL' => 'Varchar',
    ];

    private static array $has_one = [
        'Parent' => DataObjectFake::class,
    ];

    private static array $many_many = [
        'Tags' => TagFake::class,
    ];

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

    private static string $table_name = 'ImageFake';

}
