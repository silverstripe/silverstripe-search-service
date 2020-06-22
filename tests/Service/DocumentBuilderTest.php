<?php


namespace SilverStripe\SearchService\Tests\Service;

require_once(__DIR__ . '/../SearchServiceTest.php');

use PhpParser\Comment\Doc;
use SilverStripe\Control\Controller;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Tests\Fake\DocumentFake;
use SilverStripe\SearchService\Tests\Fake\FakeFetchCreator;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class DocumentBuilderTest extends SearchServiceTest
{
    public function testToArray()
    {
        DocumentFake::$count = 0;
        $config = $this->mockConfig();
        $builder = new DocumentBuilder($config, DocumentFetchCreatorRegistry::singleton());
        $arr = $builder->toArray(new DocumentFake('Fake', [
            'field1' => 'value1',
            'field2' => 'value2',
        ]));

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('source_class', $arr);
        $this->assertArrayHasKey('field1', $arr);
        $this->assertArrayHasKey('field2', $arr);

        $this->assertEquals('Fake--0', $arr['id']);
        $this->assertEquals('Fake', $arr['source_class']);
        $this->assertEquals('value1', $arr['field1']);
        $this->assertEquals('value2', $arr['field2']);
    }

    public function testFromArray()
    {
        $config = $this->mockConfig();
        $registry = DocumentFetchCreatorRegistry::singleton();
        $registry->addFetchCreator(new FakeFetchCreator());
        $builder = new DocumentBuilder($config, $registry);

        $document = $builder->fromArray([
            'source_class' => 'Fake',
            'field1' => 'tester',
        ]);

        $this->assertNotNull($document);
        $this->assertInstanceOf(DocumentFake::class, $document);
        $this->assertArrayHasKey('field1', $document->fields);
        $this->assertEquals('tester', $document->fields['field1']);

        $document = $builder->fromArray([
            'source_class' => Controller::class,
            'field1' => 'tester',
        ]);

        $this->assertNull($document);
    }
}
