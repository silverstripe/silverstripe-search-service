<?php

namespace SilverStripe\SearchService\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\Traits\ServiceAware;

/**
 * Syncs index settings to a search service.
 *
 * Note this runs on dev/build automatically but is provided separately for uses where dev/build is slow (e.g 100,000+
 * record tables)
 */
class SearchConfigure extends BuildTask
{

    use ServiceAware;

    protected $title = 'Search Service Configure'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    protected $description = 'Sync search index configuration'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    private static $segment = 'SearchConfigure'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public function __construct(IndexingInterface $searchService)
    {
        parent::__construct();

        $this->setIndexService($searchService);
    }

    /**
     * @param HTTPRequest $request
     * @throws IndexingServiceException
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->getIndexService()->configure();

        echo 'Done.';
    }

}
