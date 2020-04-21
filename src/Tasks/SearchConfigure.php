<?php

namespace SilverStripe\SearchService\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\SearchService\Service\SearchService;

/**
 * Syncs index settings to a search service.
 *
 * Note this runs on dev/build automatically but is provided seperately for
 * uses where dev/build is slow (e.g 100,000+ record tables)
 */
class SearchConfigure extends BuildTask
{
    protected $title = 'Search Service Configure';

    protected $description = 'Sync search index configuration';

    private static $segment = 'SearchConfigure';

    public function run($request)
    {
        $service = Injector::inst()->get(SearchService::class);
        $service->build();

        echo 'Done.';
    }
}
