<?php


namespace SilverStripe\SearchService\Interfaces;

interface DocumentFetchCreatorInterface
{
    /**
     * @param string $class
     * @return bool
     */
    public function appliesTo(string $class): bool;

    /**
     * @param string $class
     * @param int|null $until
     * @return DocumentFetcherInterface
     */
    public function createFetcher(string $class, ?int $until = null): DocumentFetcherInterface;
}
