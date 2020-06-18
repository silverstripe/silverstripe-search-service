<?php


namespace SilverStripe\SearchService\DataObject;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Service\IndexConfiguration;
use InvalidArgumentException;

class DataObjectFetcher implements DocumentFetcherInterface
{
    use Extensible;
    use Configurable;
    use Injectable;
    use ConfigurationAware;

    /**
     * @var string
     */
    private $dataObjectClass;

    /**
     * @var int|null
     */
    private $until;

    /**
     * @var array
     */
    private static $dependencies = [
        'Configuration' => '%$' . IndexConfiguration::class,
        'Registry' => '%$' . DocumentFetchCreatorRegistry::class,
    ];

    /**
     * DataObjectFetcher constructor.
     * @param string $class
     * @param int|null $until
     */
    public function __construct(string $class, ?int $until = null)
    {
        if (!is_subclass_of($class, DataObject::class)) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a subclass of %s',
                $class,
                DataObject::class
            ));
        }

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
     * @param array $data
     * @return DocumentInterface|null
     */
    public function createDocument(array $data): ?DocumentInterface
    {
        $idField = DataObjectDocument::config()->get('record_id_field');
        $ID = $data[$idField] ?? null;

        if (!$ID) {
            throw new InvalidArgumentException(sprintf(
                'No %s field found: %s',
                $idField,
                print_r($data, true)
            ));
        }

        $dataObject = DataObject::get_by_id($this->dataObjectClass, $ID);
        if (!$dataObject) {
            return null;
        }

        return DataObjectDocument::create($dataObject);
    }

    /**
     * @param int|null $limit
     * @param int|null $offset
     * @return DataList
     */
    private function createDataList(?int $limit = null, ?int $offset = null): DataList
    {
        /* @var DBDatetime $since */
        $since = DBField::create_field('Datetime', $this->until);
        $date = $since->Rfc822();
        $list = DataList::create($this->dataObjectClass)
            ->where(
                ['SearchIndexed IS NULL OR SearchIndexed < ?' => $date]
            );
        return $list->limit($limit, $offset);
    }
}
