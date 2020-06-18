<?php


namespace SilverStripe\SearchService\Tests\Schema;


use SilverStripe\Dev\SapphireTest;
use SilverStripe\SearchService\Schema\Field;

class FieldTest extends SapphireTest
{
    public function testValueObject()
    {
        $field = new Field('foo', 'bar', ['baz' => 'qux']);
        $this->assertEquals('foo', $field->getSearchFieldName());
        $this->assertEquals('bar', $field->getProperty());
        $this->assertEquals('qux', $field->getOption('baz'));
        $this->assertNull($field->getOption('fail'));
        $field->setSearchFieldName('bar');
        $field->setProperty('foo');
        $this->assertEquals('bar', $field->getSearchFieldName());
        $this->assertEquals('foo', $field->getProperty());
        $field->setOption('baz', 'test');
        $this->assertEquals('test', $field->getOption('baz'));
        $field->setOption('option', 'test');
        $this->assertEquals('test', $field->getOption('option'));
    }
}
