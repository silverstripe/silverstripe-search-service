<?php

namespace SilverStripe\SearchService\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\ServiceAware;

class SearchInspect extends BuildTask
{
    use ServiceAware;

    private static $segment = 'SearchInspect';

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
        $builder = DocumentBuilder::create($item);
        echo '### LOCAL FIELDS' . PHP_EOL;
        echo '<pre>';
        print_r($builder->exportAttributes());

        echo '### REMOTE FIELDS ###' . PHP_EOL;
        print_r($indexer->getObject($item));

        echo '### INDEX SETTINGS ### '. PHP_EOL;
        foreach ($item->getSearchIndexes() as $index) {
            print_r($index->getSettings());
        }

        echo PHP_EOL . 'Done.' . PHP_EOL;
    }

}
