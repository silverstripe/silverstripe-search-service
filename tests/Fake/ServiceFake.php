<?php


namespace SilverStripe\SearchService\Tests\Fake;


use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\DocumentBuilder;

class ServiceFake implements IndexingInterface
{

    public $shouldError = false;

    public $documents = [];

    public function addDocument(DocumentInterface $item): IndexingInterface
    {
        $this->documents[$item->getIdentifier()] = DocumentBuilder::singleton()->toArray($item);
        return $this;
    }

    public function addDocuments(array $items): BatchDocumentInterface
    {
        foreach ($items as $item) {
            $this->addDocument($item);
        }

        return $this;
    }

    public function removeDocuments(array $items): BatchDocumentInterface
    {
        foreach ($items as $item) {
            $this->removeDocument($item);
        }

        return $this;
    }

    public function removeDocument(DocumentInterface $doc): IndexingInterface
    {
        unset($this->documents[$doc->getIdentifier()]);
        return $this;
    }

    public function getDocument(string $id): ?DocumentInterface
    {
        $doc = $this->documents[$id] ?? null;
        if (!$doc) {
            return null;
        }

        return DocumentBuilder::singleton()->fromArray($doc);
    }

    public function getDocuments(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            if ($doc = $this->getDocument($id)) {
                $results[] = $doc;
            }
        }

        return $results;
    }

    public function listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array
    {
        $docs = array_slice($this->documents, $offset, $limit);
        return array_map(function ($arr) {
            return DocumentBuilder::singleton()->fromArray($arr);
        }, $docs);
    }

    public function getDocumentTotal(string $indexName): int
    {
        return count($this->documents);
    }

    public function configure(): void
    {
        return;
    }

    public function validateField(string $field): void
    {
        return;
    }

}
