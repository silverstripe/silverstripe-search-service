<?php


namespace SilverStripe\SearchService\Tests\Fake;


use SilverStripe\SearchService\Interfaces\DependencyTracker;
use SilverStripe\SearchService\Interfaces\DocumentInterface;

class DocumentFake implements DocumentInterface, DependencyTracker
{
    public $sourceClass;
    public $index = true;
    public $isIndexed = false;
    public $fields = [];
    public $id;
    public $dependentDocuments = [];

    public static $count = 0;

    public function __construct(string $class, array $fields = [])
    {
        $this->sourceClass = $class;
        $this->fields = $fields;
        $this->id = $fields['id'] ?? $class . '--' . static::$count;
        static::$count++;
    }

    public function toArray(): array
    {
        return $this->fields;
    }

    public function getSourceClass(): string
    {
        return $this->sourceClass;
    }

    public function getIdentifier(): string
    {
        return $this->fields['id'] ?? $this->id;
    }

    public function markIndexed(): void
    {
        $this->isIndexed = true;
    }

    public function shouldIndex(): bool
    {
        return $this->index;
    }

    public function getDependentDocuments(): array
    {
        return $this->dependentDocuments;
    }
}
