<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Dev\TestOnly;
use Wilr\SilverStripe\Algolia\Service\SearchService;

class TestSearchService extends SearchService implements TestOnly
{
    public function getClient()
    {
        return new TestSearchServiceClient();
    }
}
