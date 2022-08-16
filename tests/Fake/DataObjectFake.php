<?php

namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\Security\Member;
use stdClass;

/**
 * @property string $Title
 * @property int $ShowInSearch
 * @property int $Sort
 * @mixin SearchServiceExtension
 */
class DataObjectFake extends DataObject implements TestOnly
{

    private static array $db = [
        'Title' => 'Varchar',
        'ShowInSearch' => 'Boolean',
        'Sort' => 'Int',
    ];

    private static array $casting = [
        'getDBHTMLText' => 'HTMLText',
    ];

    private static array $many_many = [
        'Tags' => TagFake::class,
    ];

    private static array $has_many = [
        'Images' => ImageFake::class,
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static string $default_sort = 'Sort ASC';

    private static string $table_name = 'DataObjectFake';

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

    public function canView($member = null) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return true;
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

    public function getCustomGetterObj(): stdClass
    {
        return new stdClass();
    }

    public function getCustomGetterDataObj(): self
    {
        return new self();
    }

    public function getAMultiLineString(): string
    {
        return <<<'TXT'
a
multi
line
string
TXT;
    }

    public function getDBHTMLText(): string
    {
        return "<h1>WHAT ARE WE YELLING ABOUT?</h1> Then a break <br />Then a new line\nand a tab\t";
    }

    public function getHTMLString(): string
    {
        return $this->getDBHTMLText();
    }

}
