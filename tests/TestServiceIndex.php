<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Dev\TestOnly;

class TestServiceIndex implements TestOnly
{
    public function setSettings($settings)
    {
        return $settings;
    }

    public function search($query, $requestOptions = array())
    {
        return [
            'hits' => [],
            'page' => 1,
            'nbHits' => 1,
            'hitsPerPage' => 10
        ];
    }
}
