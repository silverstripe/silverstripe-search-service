<?php


namespace SilverStripe\SearchService\Tests\Fake;


use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;

class FakeFetcher implements DocumentFetcherInterface
{
    public static $records = [];

    public static function load(int $count)
    {
        for ($i = 0; $i < $count; $i++) {
            static::$records[] = new DocumentFake('Fake', ['field' => $i]);
        }
    }
    public function fetch(int $limit, int $offset): array
    {
        return array_slice(static::$records, $offset, $limit);
    }

    public function createDocument(array $data): ?DocumentInterface
    {
        return new DocumentFake('Fake', $data);
    }

    public function getTotalDocuments(): int
    {
        return count(static::$records);
    }
}
