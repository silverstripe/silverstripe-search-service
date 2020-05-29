<?php


namespace SilverStripe\SearchService\DataObject;


use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Map;
use SilverStripe\ORM\RelationList;
use SilverStripe\ORM\SS_List;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Extensions\DBFieldExtension;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DependencyTracker;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\ConfigurationAware;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\PageCrawler;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ViewableData;
use Exception;

class DataObjectDocument implements DocumentInterface, DependencyTracker
{
    use Injectable;
    use Extensible;
    use Configurable;
    use ConfigurationAware;

    /**
     * @var string
     * @config
     */
    private static $id_field = 'record_id';

    /**
     * @var string
     * @config
     */
    private static $base_class_field = 'record_base_class';

    /**
     * @var string
     * @config
     */
    private static $page_content_field = 'page_content';

    /**
     * @var DataObject&SearchServiceExtension
     */
    private $dataObject;

    /**
     * @var IndexingInterface
     */
    private $service;

    /**
     * @var PageCrawler
     */
    private $pageCrawler;

    /**
     * @var array
     */
    private static $dependencies = [
        'Service' => '%$' . IndexingInterface::class,
        'PageCrawler' => '%$' . PageCrawler::class,
        'Configuration' => '%$' . IndexConfiguration::class,
    ];

    /**
     * DataObjectDocument constructor.
     * @param DataObject $dataObject
     */
    public function __construct(DataObject $dataObject)
    {
        $this->setDataObject($dataObject);
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        $type = str_replace('\\', '_', $this->getDataObject()->baseClass());
        $id = $this->getDataObject()->ID;

        return strtolower(sprintf('%s_%s', $type, $id));
    }

    /**
     * @return bool
     */
    public function shouldIndex(): bool
    {
        if ($this->getDataObject()->hasField('ShowInSearch') && !$this->getDataObject()->ShowInSearch) {
            return false;
        }
        if (!$this->getConfiguration()->isEnabled()) {
            return false;
        }

        $results = $this->getDataObject()->invokeWithExtensions('canIndexInSearch');

        if (!empty($results)) {
            return min($results) != false;
        }

        return true;
    }

    /**
     *
     */
    public function markIndexed(): void
    {
        $schema = DataObject::getSchema();
        $table = $schema->tableForField($this->getDataObject()->ClassName, 'SearchIndexed');

        if ($table) {
            DB::query(sprintf('UPDATE %s SET SearchIndexed = NOW() WHERE ID = %s', $table, $this->getDataObject()->ID));

            if ($this->getDataObject()->hasExtension(Versioned::class) && $this->getDataObject()->hasStages()) {
                DB::query(sprintf(
                    'UPDATE %s_Live SET SearchIndexed = NOW() WHERE ID = %s',
                    $table,
                    $this->getDataObject()->ID
                ));
            }
        }
    }

    /**
     * @return array
     */
    public function getIndexes(): array
    {
        return $this->getConfiguration()->getIndexesForClassName(
            get_class($this->getDataObject())
        );
    }

    /**
     * Generates a map of all the fields and values which will be sent.
     * @return array
     * @throws IndexConfigurationException
     */
    public function toArray(): array
    {
        $idField = $this->config()->get('id_field');
        $baseClassField = $this->config()->get('base_class_field');
        $pageContentField = $this->config()->get('page_content_field');
        $dataObject = $this->getDataObject();

        $toIndex = [];
        $toIndex[$idField] = $dataObject->ID;
        $toIndex[$baseClassField] = $dataObject->baseClass();

        if ($this->getPageCrawler() && $this->getConfiguration()->shouldCrawlPageContent()) {
            $toIndex[$pageContentField] = $this->getPageCrawler()->getMainContent($dataObject);
        }

        $dataObject->invokeWithExtensions('onBeforeAttributesFromObject');

        $attributes = new Map(ArrayList::create());

        foreach ($toIndex as $k => $v) {
            $this->getService()->validateField($k);
            $attributes->push($k, $v);
        }

        foreach ($this->getIndexedFields() as $searchFieldName => $spec) {
            /* @var DBField&DBFieldExtension $dbField */
            $dbField = $this->toDBField($searchFieldName, $spec);
            if ($dbField && ($dbField->exists() || $dbField instanceof DBBoolean)) {
                if ($dbField instanceof RelationList || $dbField instanceof DataObject) {
                    // has-many, many-many, has-one
                    $this->exportAttributesFromRelationship($searchFieldName, $attributes);
                } else {
                    $value = $dbField->getSearchValue();
                    $attributes->push($searchFieldName, $value);
                }
            }
        }

        // DataObject specific customisation
        $dataObject->invokeWithExtensions('updateSearchAttributes', $attributes);

        // Universal customisation
        $this->extend('updateSearchAttributes', $attributes);

        return $attributes->toArray();
    }

    /**
     * Retrieve all the attributes from the related object that we want to add
     * to this record.
     *
     * @param string $relationship
     * @param Map $attributes
     */
    public function exportAttributesFromRelationship($relationship, $attributes): void
    {
        $item = $this->getDataObject();
        try {
            $data = [];

            /* @var ViewableData $related */
            $related = $item->{$relationship}();

            if (!$related || !$related->exists()) {
                return;
            }
            $relatedRecords = is_iterable($related) ? $related : [$related];
            foreach ($relatedRecords as $relatedObj) {
                $document = DataObjectDocument::create($relatedObj);
                $data[] = $document->toArray();
            }
            $attributes->push($relationship, $data);
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);
        }
    }

    /**
     * @return array
     * @throws IndexConfigurationException
     */
    public function getDependentDocuments(): array
    {
        $dependencies = [];
        $data = $this->toArray();
        $idField = $this->config()->get('id_field');
        $dependencies[] = $data[$idField];
        foreach ($this->getIndexedFields() as $searchFieldName => $spec) {
            $dbField = $this->toDBField($searchFieldName, $spec);
            if (!$dbField) {
                continue;
            }
            if ($dbField instanceof RelationList || $dbField instanceof DataObject) {
                $relations = $dbField instanceof DataObject ? [$dbField] : $dbField;
                foreach ($relations as $relatedRecord) {
                    $dependencies[] = $
                    $doc = DataObjectDocument::create($relatedRecord);
                    $dependencies = array_merge($dependencies, $doc->getDependentDocuments());
                }
            }
        }

        return $dependencies;
    }

    /**
     * @return array
     */
    public function getIndexedFields(): array
    {
        $candidate = get_class($this->dataObject);
        $specs = null;
        while (!$specs && $candidate !== DataObject::class) {
            $specs = $this->getConfiguration()->getFieldsForClass($candidate);
            $candidate = get_parent_class($candidate);
        }

        $fields = [];
        if ($specs) {
            foreach ($specs as $searchFieldName => $spec) {
                if ($spec === false) {
                    continue;
                }
                $fields[$searchFieldName] = $spec;
            }
        }

        return $fields;
    }

    /**
     * @param string $fieldName
     * @return DBField|null
     */
    private function resolveField(string $fieldName): ?DBField
    {
        // First try a match on a field or method
        $dataObject = $this->getDataObject();
        $result = $dataObject->obj($fieldName);
        if ($result && $result instanceof DBField) {
            return $result->getValue();
        }
        $normalFields = array_keys(
            DataObject::getSchema()
                ->fieldSpecs($dataObject, DataObjectSchema::DB_ONLY)
        );

        $lowercaseFields = array_map('strtolower', $normalFields);
        $lookup = array_combine($lowercaseFields, $normalFields);
        $fieldName = $lookup[strtolower($fieldName)] ?? null;

        return $fieldName ? $dataObject->obj($fieldName) : null;
    }

    /**
     * @param string $searchFieldName
     * @param $spec
     * @return DBField|DataObject|SS_List
     */
    public function toDBField(string $searchFieldName, $spec)
    {
        if (is_array($spec) && isset($spec['property'])) {
            /* @var DBField&DBFieldExtension $result */
            return $this->getDataObject()->obj($spec['property']);
        }

        return $this->resolveField($searchFieldName);
    }

    public function getRefererDataObjects()
    {
        $searchableClasses = $this->getConfiguration()->getSearchableClasses();
        $dataObjectClasses = array_filter($searchableClasses, function ($class) {
            return is_subclass_of($class, DataObject::class);
        });
        foreach ($dataObjectClasses as $class) {
            $dataobject = Injector::inst()->get($class);
            $document = DataObjectDocument::create($dataobject);
            $fields = $this->getConfiguration()->getFieldsForClass($class);
            foreach ($fields as $searchFieldName => $spec) {
                $dbField = $document->toDBField($searchFieldName, $spec);
                if ($dbField instanceof RelationList) {
                    /* @var RelationList $dbField */
                    $relatedObj = Injector::inst()->get($dbField->dataClass());
                    if (!$dataobject instanceof $relatedObj) {
                        continue;
                    }
                    if (!$dbField->filter('ID', $dataobject->ID)->exists()) {
                        continue;
                    }

                    yield $document->getDataObject();
                } else if ($dbField instanceof DataObject) {
                    $objectClass = get_class($dbField);
                    if (!$dataobject instanceof $objectClass) {
                        continue;
                    }
                    yield $document->getDataObject();
                }
            }
        }
    }

    /**
     * @return DataObject&SearchServiceExtension
     */
    public function getDataObject(): DataObject
    {
        return $this->dataObject;
    }

    /**
     * @param DataObject&SearchServiceExtension $dataObject
     * @return DataObjectDocument
     */
    public function setDataObject(DataObject $dataObject)
    {
        $this->dataObject = $dataObject;
        return $this;
    }

    /**
     * @return IndexingInterface
     */
    public function getService(): IndexingInterface
    {
        return $this->service;
    }

    /**
     * @param IndexingInterface $service
     * @return DataObjectDocument
     */
    public function setService(IndexingInterface $service): DataObjectDocument
    {
        $this->service = $service;
        return $this;
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


}
