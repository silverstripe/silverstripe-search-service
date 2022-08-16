<?php

namespace SilverStripe\SearchService\DataObject;

use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;

class DataObjectFetcher implements DocumentFetcherInterface
{

    use Extensible;
    use Configurable;
    use Injectable;
    use ConfigurationAware;

    private ?string $dataObjectClass = null;

    private static array $dependencies = [
        'Configuration' => '%$' . IndexConfiguration::class,
        'Registry' => '%$' . DocumentFetchCreatorRegistry::class,
    ];

    public function __construct(string $class)
    {
        if (!is_subclass_of($class, DataObject::class)) {
            throw new InvalidArgumentException(sprintf(
                '%s is not a subclass of %s',
                $class,
                DataObject::class
            ));
        }

        $this->dataObjectClass = $class;
    }

    /**
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

    public function getTotalDocuments(): int
    {
        return $this->createDataList()->count();
    }

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

    private function createDataList(?int $limit = null, ?int $offset = null): DataList
    {
        $list = DataList::create($this->dataObjectClass);

        return $list->limit($limit, $offset);
    }

}
