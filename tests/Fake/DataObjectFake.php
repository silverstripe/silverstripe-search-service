<?php


namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\Security\Member;

class DataObjectFake extends DataObject implements TestOnly
{
    public $can_view;

    private static $db = [
        'Title' => 'Varchar',
        'ShowInSearch' => 'Boolean',
        'Sort' => 'Int'
    ];

    private static $many_many = [
        'Tags' => TagFake::class,
    ];

    private static $has_many = [
        'Images' => ImageFake::class,
    ];

    private static $has_one = [
        'Member' => Member::class,
    ];

    private static $default_sort = 'Sort ASC';

    private static $extensions = [
        SearchServiceExtension::class,
    ];

    public function canView($member = null)
    {
        if (is_callable($this->can_view)) {
            $func = $this->can_view;
            return $func();
        }
        return $this->can_view;
    }

    public function getCustomGetterString(): string
    {
        return 'custom-getter';
    }

    public function getCustomGetterArray(): array
    {
        return ['one', 'two'];
    }

    public function getCustomGetterMixedArray(): array
    {
        return ['one' => new self(), 'two' => ['three']];
    }
    public function getCustomGetterMap(): array
    {
        return ['one' => 'two', 'three' => 'four'];
    }

    public function getCustomGetterObj(): \stdClass
    {
        return new \stdClass();
    }

    public function getCustomGetterDataObj(): self
    {
        return new self();
    }
}
