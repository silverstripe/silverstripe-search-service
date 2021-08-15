<?php

namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\Versioned\Versioned;

class VersionedDataObjectFake extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedDataObjectFake';

    private static $extensions = [
        Versioned::class,
        SearchServiceExtension::class,
    ];

    public $can_view;

    private static $db = [
        'ShowInSearch' => 'Boolean',
    ];

    public function canView($member = null)
    {
        if (is_callable($this->can_view)) {
            $func = $this->can_view;
            return $func();
        }
        return $this->can_view;
    }
}
