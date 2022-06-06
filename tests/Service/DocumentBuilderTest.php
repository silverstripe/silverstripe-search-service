<?php


namespace SilverStripe\SearchService\Tests\Service;

use PhpParser\Comment\Doc;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Service\EnterpriseSearch\EnterpriseSearchService;
use SilverStripe\SearchService\Tests\Fake\DocumentFake;
use SilverStripe\SearchService\Tests\Fake\FakeFetchCreator;
use SilverStripe\SearchService\Tests\Fake\ServiceFake;
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

    public function testDocumentTruncation()
    {
        $fake = new ServiceFake();
        $fake->maxDocSize = 100;

        Injector::inst()->registerService($fake, IndexingInterface::class);

        $builder = DocumentBuilder::create();
        $document = new DocumentFake('Fake', [
            'field1' => str_repeat('a', 500),
        ]);
        $array = $builder->toArray($document);
        $postData = json_encode($array, JSON_PRESERVE_ZERO_FRACTION);
        $this->assertNotFalse($postData, 'Document failed to successfully json_encode');
        $this->assertLessThanOrEqual($fake->maxDocSize, strlen($postData));

        $document = new DocumentFake('Fake', [
            'field1' => str_repeat('a', 50),
        ]);

        // Try a couple different doc sizes that far exceed the size of this document
        $fake->maxDocSize = 10000;
        $array = $builder->toArray($document);
        $postData = json_encode($array, JSON_PRESERVE_ZERO_FRACTION);
        $this->assertNotFalse($postData, 'Document failed to successfully json_encode');
        $size1 = strlen($postData);

        $fake->maxDocSize = 5000;
        $array = $builder->toArray($document);
        $postData = json_encode($array, JSON_PRESERVE_ZERO_FRACTION);
        $this->assertNotFalse($postData, 'Document failed to successfully json_encode');
        $size2 = strlen($postData);

        $this->assertEquals($size1, $size2);

        // Try a non-latin document with awkward splits
        $fake->maxDocSize = 53;
        $document = new DocumentFake('Fake', [
            'field1' => str_repeat('日', 117),
        ]);
        $array = $builder->toArray($document);
        $postData = json_encode($array, JSON_PRESERVE_ZERO_FRACTION);
        $this->assertNotFalse($postData, 'Document failed to successfully json_encode');
        $this->assertLessThanOrEqual($fake->maxDocSize, strlen($postData));
    }
}
