<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Dev\TestOnly;

class TestSearchServiceClient implements TestOnly
{
    public function initIndex($name)
    {
        return new TestServiceIndex();
    }
}
