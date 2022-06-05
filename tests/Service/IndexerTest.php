<?php


namespace SilverStripe\SearchService\Tests\Service;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\SearchService\Tests\Fake\DataObjectDocumentFake;
use SilverStripe\SearchService\Tests\Fake\DocumentFake;
use SilverStripe\SearchService\Tests\Fake\ServiceFake;
use SilverStripe\SearchService\Tests\Fake\TagFake;
use SilverStripe\SearchService\Tests\SearchServiceTest;

class IndexerTest extends SearchServiceTest
{
    public function testConstructor()
    {
        $config = $this->mockConfig();
        $config->set('batch_size', 7);
        $indexer = new Indexer(
            [new DocumentFake('Fake'), new DocumentFake('Fake')]
        );
        $this->assertCount(2, $indexer->getDocuments());
        $this->assertEquals(Indexer::METHOD_ADD, $indexer->getMethod());
        $this->assertEquals(7, $indexer->getBatchSize());
    }

    public function testChunking()
    {
        $config = $this->mockConfig();
        $config->set('batch_size', 7);
        $docs = [];
        for ($i = 0; $i < 20; $i++) {
            $docs[] = new DocumentFake('Fake');
        }
        $indexer = new Indexer($docs);
        $this->assertEquals(3, $indexer->getChunkCount());
        $docs[] = new DocumentFake('Fake');
        $docs[] = new DocumentFake('Fake');
        $indexer->setDocuments($docs);
        $this->assertEquals(4, $indexer->getChunkCount());

        $indexer->setBatchSize(5);
        $this->assertEquals(5, $indexer->getChunkCount());

        $indexer->setBatchSize(2);
        $this->assertEquals(11, $indexer->getChunkCount());

        $indexer->setBatchSize(50);
        $this->assertEquals(1, $indexer->getChunkCount());
    }

    public function testIndexing()
    {
        $config = $this->mockConfig();
        $config->set('batch_size', 7);
        $config->set('isClassIndexed', [
            'Fake' => true
        ]);

        $docs = [];
        for ($i = 0; $i < 20; $i++) {
            $docs[] = new DocumentFake('Fake');
        }
        $docs[0]->index = false;

        $indexer = new Indexer($docs);
        $indexer->setIndexService($service = new ServiceFake());

        $this->assertEquals(3, $indexer->getChunkCount());

        $indexer->processNode();
        $this->assertFalse($indexer->finished());

        $indexer->processNode();
        $this->assertFalse($indexer->finished());

        $indexer->processNode();
        $this->assertTrue($indexer->finished());

        $this->assertCount(19, $service->documents);
    }

    public function testCleanup()
    {
        $config = $this->mockConfig();
        $config->set('batch_size', 7);
        $config->set('isClassIndexed', [
            'Fake' => true
        ]);

        $docs = [];
        for ($i = 0; $i < 20; $i++) {
            $docs[] = new DocumentFake('Fake', ['id' => 'node-' . $i]);
        }
        $indexer = new Indexer($docs);
        $indexer->setIndexService($service = new ServiceFake());
        $indexer->setBatchSize(50);
        $indexer->processNode();
        $this->assertTrue($indexer->finished());
        $this->assertCount(20, $service->documents);

        // Now add a few more documents, and update two, and ensure two are removed.
        $docs = [
            new DocumentFake('Fake', ['id' => 'new-1']),
            new DocumentFake('Fake', ['id' => 'new-2']),
            new DocumentFake('Fake', ['id' => 'node-0', 'updated' => '1']),
            new DocumentFake('Fake', ['id' => 'node-1', 'updated' => '2']),
            $remove1 = new DocumentFake('Fake', ['id' => 'node-2']),
            $remove2 = new DocumentFake('Fake', ['id' => 'node-3']),
            $remove3 = new DocumentFake('Fake', ['id' => 'node-4']),
        ];
        $remove1->index = false;
        $remove2->index = false;
        $remove3->index = false;

        $indexer->setDocuments($docs);
        $indexer->processNode();

        $this->assertTrue($indexer->finished());
        $this->assertCount(19, $service->documents);

        $ids = array_keys($service->documents);

        $this->assertContains('new-1', $ids);
        $this->assertContains('new-2', $ids);
        $this->assertContains('node-0', $ids);
        $this->assertContains('node-1', $ids);
        $this->assertNotContains('node-2', $ids);
        $this->assertNotContains('node-3', $ids);
        $this->assertNotContains('node-4', $ids);

        $this->assertArrayHasKey('updated', $service->documents['node-0']);
        $this->assertArrayHasKey('updated', $service->documents['node-1']);
        $this->assertEquals('1', $service->documents['node-0']['updated']);
        $this->assertEquals('2', $service->documents['node-1']['updated']);
    }

    public function testDependentDocuments()
    {
        Injector::inst()->registerService($service = new ServiceFake(), IndexingInterface::class);
        $config = $this->mockConfig();
        $config->set('isClassIndexed', [
            DataObjectDocumentFake::class => true,
        ]);
        $config->set('auto_dependency_tracking', true);

        $blog1 = new DocumentFake(DataObjectDocumentFake::class, ['id' => 'blog-1']);
        $blog2 = new DocumentFake(DataObjectDocumentFake::class, ['id' => 'blog-2']);

        $tagDocs = [
            $tag1 = new DocumentFake(TagFake::class, ['id' => 'tag-1']),
            $tag2 = new DocumentFake(TagFake::class, ['id' => 'tag-2']),
        ];
        $tag2->dependentDocuments = [
            $blog1,
            $blog2,
        ];
        $indexer = Indexer::create($tagDocs);
        $indexer->processNode();
        $this->assertTrue($indexer->finished());

        $this->assertCount(2, $service->documents);
        $this->assertArrayHasKey('blog-1', $service->documents);
        $this->assertArrayHasKey('blog-2', $service->documents);

        $blog1->index = false;

        $indexer = Indexer::create($tagDocs);
        $indexer->processNode();
        $this->assertTrue($indexer->finished());

        $this->assertCount(1, $service->documents);
        $this->assertArrayHasKey('blog-2', $service->documents);
    }
}
