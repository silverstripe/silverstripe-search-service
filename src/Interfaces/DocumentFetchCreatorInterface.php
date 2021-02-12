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
     * @return DocumentFetcherInterface
     */
    public function createFetcher(string $class): DocumentFetcherInterface;
}
