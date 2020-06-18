<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\SearchService\Service\SearchService;

class TestSearchService extends SearchService implements TestOnly
{
    public function getClient()
    {
        return new TestSearchServiceClient();
    }
}
