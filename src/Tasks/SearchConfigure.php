<?php

namespace SilverStripe\SearchService\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\SearchService;
use SilverStripe\SearchService\Service\ServiceAware;

/**
 * Syncs index settings to a search service.
 *
 * Note this runs on dev/build automatically but is provided seperately for
 * uses where dev/build is slow (e.g 100,000+ record tables)
 */
class SearchConfigure extends BuildTask
{
    use ServiceAware;

    protected $title = 'Search Service Configure';

    protected $description = 'Sync search index configuration';

    private static $segment = 'SearchConfigure';


    public function __construct(SearchServiceInterface $searchService)
    {
        $this->setSearchService($searchService);
    }

    public function run($request)
    {
        $this->getSearchService()->configure();
        echo 'Done.';
    }

}
