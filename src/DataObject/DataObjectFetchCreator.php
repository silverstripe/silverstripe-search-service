<?php

namespace SilverStripe\SearchService\DataObject;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Interfaces\DocumentFetchCreatorInterface;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;

class DataObjectFetchCreator implements DocumentFetchCreatorInterface
{

    use Injectable;

    public function appliesTo(string $type): bool
    {
        return is_subclass_of($type, DataObject::class);
    }

    public function createFetcher(string $class): DocumentFetcherInterface
    {
        return DataObjectFetcher::create($class);
    }

}
