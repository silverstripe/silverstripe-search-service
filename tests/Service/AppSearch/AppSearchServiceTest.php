<?php


namespace SilverStripe\SearchService\Tests\Service\AppSearch;

use Elastic\AppSearch\Client\Client;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Services\AppSearch\AppSearchService;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\DocumentFake;
use SilverStripe\SearchService\Tests\Fake\FakeFetchCreator;
use SilverStripe\SearchService\Tests\Fake\ImageFake;
use SilverStripe\SearchService\Tests\Fake\IndexConfigurationFake;
use SilverStripe\SearchService\Tests\Fake\TagFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;
use SilverStripe\Security\Member;

class AppSearchServiceTest extends SearchServiceTest
{
    protected static $fixture_file = '../../fixtures.yml';

    protected static $extra_dataobjects = [
        DataObjectFake::class,
        TagFake::class,
        ImageFake::class,
        Member::class,
    ];

    /**
     * @var IndexConfigurationFake&\PHPUnit_Framework_MockObject_MockObject
     */
    protected $config;

    /**
     * @var AppSearchService&\PHPUnit_Framework_MockObject_MockObject
     */
    protected $appSearch;

    /**
     * @var Client&\PHPUnit_Framework_MockObject_MockObject
     */
    protected $client;

    protected function setUp()
    {
        parent::setUp();
        DocumentFake::$count = 0;
        DocumentFetchCreatorRegistry::singleton()
            ->addFetchCreator(new FakeFetchCreator());

        $this->config = $this->mockConfig();
        $this->config->set('getIndexesForClassName', [
            'Fake' => ['index1' => [],  'index2' => []]
        ]);
        $this->config->set('getIndexVariant', 'tests');
        $this->config->set('indexes', [
            'index1' => [],
            'index2' => []
        ]);

        $this->client = $this->mockClient();
        $this->appSearch = new AppSearchService(
            $this->client,
            $this->config,
            DocumentBuilder::singleton()
        );
    }

    /**
     * @dataProvider provideShouldIndex
     */
    public function testAddDocument($shouldIndex)
    {
        $fake1 = new DocumentFake('Fake', ['field' => 'value1']);
        $fake1->index = $shouldIndex;
        $doc1 = ['id' => 'Fake--0', 'source_class' => 'Fake', 'field' => 'value1'];
        $expectedDocs = [$doc1];
        if ($shouldIndex) {
            $this->client->expects($this->exactly(2))
                ->method('indexDocuments')
                ->withConsecutive(
                    [$this->equalTo('tests-index1'), $expectedDocs],
                    [$this->equalTo('tests-index2'), $expectedDocs]
                );
        } else {
            $this->client->expects($this->never())
                ->method('indexDocuments');
        }


        $this->appSearch->addDocument($fake1);
    }

    /**
     * @dataProvider provideShouldIndex
     */
    public function testAddDocuments($shouldIndex)
    {
        $fake1 = new DocumentFake('Fake', ['field' => 'value1']);
        $fake2 = new DocumentFake('Fake', ['field' => 'value2']);
        $fake2->index = $shouldIndex;

        $doc1 = ['id' => 'Fake--0', 'source_class' => 'Fake', 'field' => 'value1'];
        $doc2 = ['id' => 'Fake--1', 'source_class' => 'Fake', 'field' => 'value2'];

        $expectedDocs = $shouldIndex ? [$doc1, $doc2] : [$doc1];
        $this->client->expects($this->exactly(2))
            ->method('indexDocuments')
            ->withConsecutive(
                [$this->equalTo('tests-index1'), $expectedDocs],
                [$this->equalTo('tests-index2'), $expectedDocs]
            );

        $this->appSearch->addDocuments([$fake1, $fake2]);
    }
    
    public function testRemoveAllDocuments()
    {
        $this->client->expects($this->exactly(2))
            ->method('listDocuments')
            ->willReturnOnConsecutiveCalls(
                [
                    'results' => [
                        [ 'id' => 100 ],
                        [ 'id' => 101 ],
                        [ 'id' => 102 ]
                    ]
                ],
                [
                    'results' => []
                ]
            );

        $this->client->expects($this->exactly(1))
            ->method('deleteDocuments')
            ->willReturn([
                [ 'deleted' => true ],
                [ 'deleted' => true ],
                [ 'deleted' => true ],
            ]);

        $this->assertSame(3, $this->appSearch->removeAllDocuments('test'));
    }

    public function testRemoveDocuments()
    {
        $fake1 = new DocumentFake('Fake', ['field' => 'value1']);
        $fake2 = new DocumentFake('Fake', ['field' => 'value2']);

        $this->client->expects($this->exactly(2))
            ->method('deleteDocuments')
            ->withConsecutive(
                [$this->equalTo('tests-index1'), ['Fake--0', 'Fake--1']],
                [$this->equalTo('tests-index2'), ['Fake--0', 'Fake--1']]
            );

        $this->appSearch->removeDocuments([$fake1, $fake2]);
    }

    public function testRemoveDocument()
    {
        $fake1 = new DocumentFake('Fake', ['field' => 'value1']);
        $this->client->expects($this->exactly(2))
            ->method('deleteDocuments')
            ->withConsecutive(
                [$this->equalTo('tests-index1'), ['Fake--0']],
                [$this->equalTo('tests-index2'), ['Fake--0']]
            );

        $this->appSearch->removeDocument($fake1);
    }

    public function testGetDocuments()
    {
        $this->client->expects($this->exactly(2))
            ->method('getDocuments')
            ->withConsecutive(
                [$this->equalTo('tests-index1'), $this->equalTo(['id1','id2'])],
                [$this->equalTo('tests-index2'), $this->equalTo(['id1','id2'])]
            )
            ->willReturn(['results' => [
               ['id' => 1, 'source_class' => 'Fake', 'field' => 'value1'],
               ['id' => 2, 'source_class' => 'Fake', 'field' => 'value2']
            ]]);
        $result = $this->appSearch->getDocuments(['id1', 'id2']);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(DocumentFake::class, $result[0]);
        $this->assertInstanceOf(DocumentFake::class, $result[1]);
        $this->assertArrayHasKey('field', $result[0]->fields);
        $this->assertArrayHasKey('field', $result[1]->fields);
        $this->assertEquals('value1', $result[0]->fields['field']);
        $this->assertEquals('value2', $result[1]->fields['field']);
    }

    public function testGetDocument()
    {
        $this->client->expects($this->exactly(2))
            ->method('getDocuments')
            ->withConsecutive(
                [$this->equalTo('tests-index1'), $this->equalTo(['id2'])],
                [$this->equalTo('tests-index2'), $this->equalTo(['id2'])]
            )
            ->willReturn(['results' => [
                ['source_class' => 'Fake', 'field' => 'value2']
            ]]);
        $result = $this->appSearch->getDocument('id2');
        $this->assertNotNull($result);
        $this->assertInstanceOf(DocumentFake::class, $result);
        $this->assertArrayHasKey('field', $result->fields);
        $this->assertEquals('value2', $result->fields['field']);
    }

    public function testListDocuments()
    {
        $this->client->expects($this->once())
            ->method('listDocuments')
            ->with(
                $this->equalTo('tests-index1'),
                $this->equalTo(0),
                $this->equalTo(null)
            )
            ->willReturn(['results' => [
                ['source_class' => 'Fake', 'field' => 'value1'],
                ['source_class' => 'Fake', 'field' => 'value2']
            ]]);
        $result = $this->appSearch->listDocuments('index1');
        $this->assertCount(2, $result);
        $this->assertInstanceOf(DocumentFake::class, $result[0]);
        $this->assertInstanceOf(DocumentFake::class, $result[1]);
        $this->assertArrayHasKey('field', $result[0]->fields);
        $this->assertArrayHasKey('field', $result[1]->fields);
        $this->assertEquals('value1', $result[0]->fields['field']);
        $this->assertEquals('value2', $result[1]->fields['field']);
    }

    public function testGetDocumentTotal()
    {
        $this->client->expects($this->once())
            ->method('listDocuments')
            ->with('tests-index1')
            ->willReturn([
                'meta' => [
                    'page' => [
                        'total_results' => 9
                    ]
                ]
            ]);
        $result = $this->appSearch->getDocumentTotal('index1');
        $this->assertEquals(9, $result);
    }

    public function testConfigure()
    {
        $this->config->set('indexes', [
            'index1' => [],
            'index2' => [],
            'index3' => [],
            'index4' => [],
        ]);

        $this->config->set('getFieldsForIndex', [
            'index1' => [
                new Field('title'),
                new Field('created', 'created', ['type' => 'date']),
            ],
            'index2' => [
                new Field('sort', 'sort', ['type' => 'number']),
                new Field('content')
            ],
            'index3' => [
                new Field('tags'),
                new Field('photo')
            ],
            'index4' => [
                new Field('price')
            ]
        ]);
        // index4 is missing
        $this->client->expects($this->exactly(4))
            ->method('listEngines')
            ->willReturn(
                ['results' => [
                    ['name' => 'tests-index1'],
                    ['name' => 'tests-index2'],
                    ['name' => 'tests-index3'],
                ]]
            );

        // create index4
        $this->client->expects($this->once())
            ->method('createEngine')
            ->with($this->equalTo('tests-index4'));

        // check the schema for all the indexes
        $this->client->expects($this->exactly(4))
            ->method('getSchema')
            ->willReturnOnConsecutiveCalls(
                // identical, no update needed
                [
                    'title' => 'text',
                    'created' => 'date',
                ],
                // sort field isn't right
                [
                    'sort' => 'text',
                    'content' => 'text',
                ],
                // missing photo field
                [
                    'tags' => 'text',
                ],
                // index4 is empty
                []
            );

        // index1 needs no update
        $this->client->expects($this->exactly(3))
            ->method('updateSchema')
            ->withConsecutive(
                // index2 updates the sort field
                [
                    $this->equalTo('tests-index2'),
                    $this->equalTo(['sort' => 'number', 'content' => 'text'])
                ],
                // index3 adds a field
                [
                    $this->equalTo('tests-index3'),
                    $this->equalTo(['tags' => 'text', 'photo' => 'text'])
                ],
                // index4 was empty and needs a schema
                [
                    $this->equalTo('tests-index4'),
                    $this->equalTo(['price' => 'text'])
                ]
            );

        $this->appSearch->configure();
    }

    public function provideShouldIndex()
    {
        return [
            [true],
            [false]
        ];
    }
}
