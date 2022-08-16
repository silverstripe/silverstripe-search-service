<?php

namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Title
 * @property int $ShowInSearch
 * @mixin SearchServiceExtension
 * @mixin Versioned
 */
class DataObjectFakeVersioned extends DataObject implements TestOnly
{

    private static string $table_name = 'DataObjectFakeVersioned';

    private static array $extensions = [
        SearchServiceExtension::class,
        Versioned::class,
    ];

    private static array $db = [
        'Title' => 'Varchar',
        'ShowInSearch' => 'Boolean',
    ];

    public function canView(mixed $member = null): bool
    {
        return true;
    }

}
