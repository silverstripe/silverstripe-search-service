<?php

namespace SilverStripe\SearchService\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\DataObjectBuilder;
use SilverStripe\SearchService\Service\ServiceAware;

class SearchInspect extends BuildTask
{
    use ServiceAware;

    private static $segment = 'SearchInspectDataObject';

    public function __construct(IndexingInterface $searchService)
    {
        parent::__construct();
        $this->setIndexService($searchService);
    }

    public function run($request)
    {
        $itemClass = $request->getVar('ClassName');
        $itemId = $request->getVar('ID');

        if (!$itemClass || !$itemId) {
            echo 'Missing ClassName or ID';
            exit();
        }

        /* @var DataObject&SearchServiceExtension|null $item */
        $item = $itemClass::get()->byId($itemId);

        if (!$item || !$item->canView()) {
            echo 'Missing or unviewable object '. $itemClass . ' #'. $itemId;
            exit();
        }

        $this->getIndexService()->configure();
        $builder = DataObjectDocument::create($item);
        echo '### LOCAL FIELDS' . PHP_EOL;
        echo '<pre>';
        print_r($builder->toArray());

        echo '### REMOTE FIELDS ###' . PHP_EOL;
        print_r($this->getIndexService()->getDocument($builder->getIdentifier()));

//        echo '### INDEX SETTINGS ### '. PHP_EOL;
//        foreach ($item->getSearchIndexes() as $index) {
//            print_r($index->getSettings());
//        }

        echo PHP_EOL . 'Done.' . PHP_EOL;
    }

}
