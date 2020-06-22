<?php


namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\SearchService\Interfaces\DocumentFetchCreatorInterface;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;

class FakeFetchCreator implements DocumentFetchCreatorInterface
{
    public function appliesTo(string $type): bool
    {
        return $type === 'Fake';
    }

    public function createFetcher(string $class, ?int $until = null): DocumentFetcherInterface
    {
        return new FakeFetcher();
    }
}
