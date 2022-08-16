<?php

namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;

/**
 * @property string $Title
 * @mixin SearchServiceExtension
 */
class DataObjectFakePrivate extends DataObject implements TestOnly
{

    private static string $table_name = 'DataObjectFakePrivate';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

    public function canView(mixed $member = null): bool
    {
        return false;
    }

}
