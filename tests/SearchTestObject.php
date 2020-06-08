<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use Wilr\SilverStripe\Algolia\Extensions\SearchServiceExtension;

class SearchTestObject extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
        'OtherField' => 'Varchar',
        'NonIndexedField' => 'Varchar',
        'Active' => 'Boolean'
    ];

    private static $has_one = [
        'Author' => Member::class
    ];

    private static $many_many = [
        'RelatedTestObjects' => SearchTestObject::class
    ];

    private static $algolia_index_fields = [
        'OtherField',
        'Active'
    ];

    private static $extensions = [
        SearchServiceExtension::class
    ];

    private static $table_name = 'SearchTestObject';

    public function AbsoluteLink()
    {
        return Director::absoluteBaseURL();
    }

    public function getTitle()
    {
        return $this->dbObject('Title');
    }

    public function canIndexInAlgolia(): bool
    {
        return ($this->Active) ? true : false;
    }
}