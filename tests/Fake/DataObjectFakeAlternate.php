<?php


namespace SilverStripe\SearchService\Tests\Fake;


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class DataObjectFakeAlternate extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
    ];
}
