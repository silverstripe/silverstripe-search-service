<?php

namespace SilverStripe\SearchService\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;

class SearchInspect extends BuildTask
{
    private static $segment = 'SearchInspect';

    /**
     * @var SearchServiceInterface
     */
    private $searchService;

    public function __construct(SearchServiceInterface $searchService)
    {
        $this->setSearchService($searchService);
    }

    public function run($request)
    {
        $itemClass = $request->getVar('ClassName');
        $itemId = $request->getVar('ID');

        if (!$itemClass || !$itemId) {
            echo 'Missing ClassName or ID';
            exit();
        }

        /* @var DataObject|null $item */
        $item = $itemClass::get()->byId($itemId);

        if (!$item || !$item->canView()) {
            echo 'Missing or unviewable object '. $itemClass . ' #'. $itemId;
            exit();
        }

        $this->getSearchService()->configure();

        echo '### LOCAL FIELDS' . PHP_EOL;
        echo '<pre>';
        print_r($indexer->exportAttributesFromObject($item));

        echo '### REMOTE FIELDS ###' . PHP_EOL;
        print_r($indexer->getObject($item));

        echo '### INDEX SETTINGS ### '. PHP_EOL;
        foreach ($item->getSearchIndexes() as $index) {
            print_r($index->getSettings());
        }

        echo PHP_EOL . 'Done.' . PHP_EOL;
    }

    /**
     * @return SearchServiceInterface
     */
    public function getSearchService(): SearchServiceInterface
    {
        return $this->searchService;
    }

    /**
     * @param SearchServiceInterface $searchService
     * @return SearchInspect
     */
    public function setSearchService(SearchServiceInterface $searchService): SearchInspect
    {
        $this->searchService = $searchService;
        return $this;
    }


}
