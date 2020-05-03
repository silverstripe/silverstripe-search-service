<?php


namespace SilverStripe\SearchService\DataObject;


use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\PageCrawler;
use SilverStripe\Versioned\Versioned;

class DataObjectFetcher implements DocumentFetcherInterface
{
    use Extensible;
    use Configurable;
    use Injectable;

    /**
     * Include rendered markup from the object's `Link` method in the index.
     *
     * @config
     */
    private static $include_page_content = true;

    /**
     * @config
     */
    private static $attributes_blacklisted = [
        'ID',
        'Title',
        'ClassName',
        'LastEdited',
        'Created'
    ];

    private static $dependencies = [
        'PageCrawler' => '%$' . PageCrawler::class,
    ];

    /**
     * @var PageCrawler
     */
    private $pageCrawler;

    /**
     * @var DataObject|SearchServiceExtension
     */
    private $dataObject;

    /**
     * @var callable|null
     */
    private $fieldFormatter;

    /**
     * @param string $type
     * @return bool
     */
    public function appliesTo(string $type): bool
    {
        return is_subclass_of($type, DataObject::class);
    }

    /**
     * @param string $class
     * @param int|null $limit
     * @param int|null $offset
     * @return DocumentInterface[]
     */
    public function fetch(string $class, ?int $limit = 20, ?int $offset = 0): array
    {
        $list = $this->createDataList($class, $limit, $offset);
        $docs = [];
        foreach ($list as $record) {
            $docs[] = DataObjectDocument::create($record);
        }

        return $docs;
    }

    /**
     * @param string $class
     * @return int
     */
    public function getTotalDocuments(string $class): int
    {
        return $this->createDataList($class, null, null)->count();
    }

    /**
     * @param string $class
     * @param int|null $limit
     * @param int|null $offset
     * @return DataList
     */
    private function createDataList(string $class, ?int $limit, ?int $offset): DataList
    {
        /* @var DataObject&Versioned $inst */
        $inst = Injector::inst()->get($class);
        if ($inst->hasExtension(Versioned::class) && $inst->hasStages()) {
            $list = Versioned::get_by_stage(
                $class,
                Versioned::LIVE,
                'SearchIndexed IS NULL OR SearchIndexed < (NOW() - INTERVAL 2 HOUR)'
            );
        } else {
            $list = DataList::create($class);
        }

        return $list->limit($limit, $offset);
    }

    /**
     * @param string $field
     * @return string
     */
    private function formatField(string $field): string
    {
        if ($this->fieldFormatter && is_callable($this->fieldFormatter)) {
            return call_user_func_array($this->fieldFormatter, [$field]);
        }

        return $field;
    }


    /**
     * @param PageCrawler $crawler
     * @return $this
     */
    public function setPageCrawler(PageCrawler $crawler): self
    {
        $this->pageCrawler = $crawler;

        return $this;
    }

    /**
     * @return PageCrawler|null
     */
    public function getPageCrawler(): ?PageCrawler
    {
        return $this->pageCrawler;
    }

    /**
     * @return DataObject
     */
    public function getDataObject(): DataObject
    {
        return $this->dataObject;
    }

    /**
     * @param DataObject $dataObject
     * @return DataObjectFetcher
     */
    public function setDataObject(DataObject $dataObject): DataObjectFetcher
    {
        $this->dataObject = $dataObject;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getFieldFormatter(): ?callable
    {
        return $this->fieldFormatter;
    }

    /**
     * @param callable|null $fieldFormatter
     * @return DataObjectFetcher
     */
    public function setFieldFormatter(?callable $fieldFormatter): DataObjectFetcher
    {
        $this->fieldFormatter = $fieldFormatter;
        return $this;
    }


}
