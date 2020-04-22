<?php

namespace SilverStripe\SearchService\Services\AppSearch;

use Elastic\AppSearch\Client\Client;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;

class AppSearchService implements SearchServiceInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * AppSearchService constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function addDocument(DataObject $item): SearchServiceInterface
    {
        // TODO: Implement addDocument() method.
    }

    public function addDocuments(array $items): SearchServiceInterface
    {
        // TODO: Implement addDocuments() method.
    }

    public function removeDocument(DataObject $item): SearchServiceInterface
    {
        // TODO: Implement removeDocument() method.
    }

    public function build(): void
    {
        // TODO: Implement build() method.
    }
}
