<?php


namespace SilverStripe\SearchService\DataObject;


use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\Versioned\Versioned;

class DataObjectFetcher implements DocumentFetcherInterface
{
    use Extensible;
    use Configurable;
    use Injectable;

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

    /**
     * @var string
     */
    private $dataObjectClass;

    /**
     * @var int|null
     */
    private $until;

    /**
     * DataObjectFetcher constructor.
     * @param string $class
     * @param int|null $until
     */
    public function __construct(string $class, ?int $until = null)
    {
        $this->dataObjectClass = $class;
        $this->until = $until;
    }

    /**
     * @param int|null $limit
     * @param int|null $offset
     * @return DocumentInterface[]
     */
    public function fetch(?int $limit = 20, ?int $offset = 0): array
    {
        $list = $this->createDataList($limit, $offset);
        $docs = [];
        foreach ($list as $record) {
            $docs[] = DataObjectDocument::create($record);
        }

        return $docs;
    }

    /**
     * @return int
     */
    public function getTotalDocuments(): int
    {
        return $this->createDataList()->count();
    }

    /**
     * @param string $class
     * @param int|null $limit
     * @param int|null $offset
     * @return DataList
     */
    private function createDataList(?int $limit = null, ?int $offset = null): DataList
    {
        /* @var DBDatetime $since */
        $since = DBField::create_field('Datetime', $this->until);
        $date = $since->Rfc822();
        /* @var DataObject&Versioned $inst */
        $inst = Injector::inst()->get($this->dataObjectClass);
        if ($inst->hasExtension(Versioned::class) && $inst->hasStages()) {
            $list = Versioned::get_by_stage(
                $this->dataObjectClass,
                Versioned::LIVE,
                sprintf("SearchIndexed IS NULL OR SearchIndexed < '%s'", $date)
            );
        } else {
            $list = DataList::create($this->dataObjectClass);
        }

        return $list->limit($limit, $offset);
    }

}
