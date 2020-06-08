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
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Extensions\DBFieldExtension;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DependencyTracker;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Service\ConfigurationAware;
use SilverStripe\SearchService\Service\DocumentChunkFetcher;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\PageCrawler;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ViewableData;
use Exception;
use Serializable;

class DataObjectDocument implements DocumentInterface, DependencyTracker, Serializable
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
        $dataObject = $this->getDataObject();

        // If an anonymous user can't view it
        $isPublic = Member::actAs(null, function () use ($dataObject) {
            return $dataObject->canView();
        });

        if (!$isPublic) {
            return false;
        }

        // "ShowInSearch" field
        if ($dataObject->hasField('ShowInSearch') && !$dataObject->ShowInSearch) {
            return false;
        }

        // Indexing is globally disabled
        if (!$this->getConfiguration()->isEnabled()) {
            return false;
        }

        // Extension override
        $results = $dataObject->invokeWithExtensions('canIndexInSearch');

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

        foreach ($this->getIndexedFields() as $field) {
            /* @var DBField&DBFieldExtension $dbField */
            $dbField = $this->toDBField($field);
            if ($dbField && ($dbField->exists() || $dbField instanceof DBBoolean)) {
                if ($dbField instanceof RelationList || $dbField instanceof DataObject) {
                    // has-many, many-many, has-one
                    $this->exportAttributesFromRelationship($field->getSearchFieldName(), $attributes);
                } else {
                    $value = $dbField->getSearchValue();
                    $attributes->push($field->getSearchFieldName(), $value);
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
     * @return Field[]
     */
    public function getIndexedFields(): array
    {
        $candidate = get_class($this->dataObject);
        $fields = null;
        while (!$fields && $candidate !== DataObject::class) {
            $fields = $this->getConfiguration()->getFieldsForClass($candidate);
            $candidate = get_parent_class($candidate);
        }

        return $fields;
    }

    /**
     * @param string $fieldName
     * @return DBField|RelationList|null
     */
    private function resolveField(string $fieldName)
    {
        // First try a match on a field or method
        $dataObject = $this->getDataObject();
        $result = $dataObject->obj($fieldName);
        if ($result && $result instanceof DBField) {
            return $result;
        }
        $normalFields = array_merge(
            array_keys(
                DataObject::getSchema()
                    ->fieldSpecs($dataObject, DataObjectSchema::DB_ONLY)
            ),
            array_keys(
                $dataObject->hasMany()
            ),
            array_keys(
                $dataObject->manyMany()
            )
        );

        $lowercaseFields = array_map('strtolower', $normalFields);
        $lookup = array_combine($lowercaseFields, $normalFields);
        $fieldName = $lookup[strtolower($fieldName)] ?? null;

        return $fieldName ? $dataObject->obj($fieldName) : null;
    }

    /**
     * @param Field $field
     * @return DBField|DataObject|SS_List
     */
    public function toDBField(Field $field)
    {
        if ($field->getProperty()) {
            return $this->getDataObject()->obj($field->getProperty());
        }

        return $this->resolveField($field->getSearchFieldName());
    }

    /**
     * @return iterable
     */
    public function getDependentDocuments(): iterable
    {
        $searchableClasses = $this->getConfiguration()->getSearchableClasses();
        $dataObjectClasses = array_filter($searchableClasses, function ($class) {
            return is_subclass_of($class, DataObject::class);
        });
        $docs = [];
        $ownedDataObject = $this->getDataObject();
        foreach ($dataObjectClasses as $class) {
            // Start with a singleton to look at the model first, then get real records if needed
            $owningDataObject = Injector::inst()->get($class);

            $document = DataObjectDocument::create($owningDataObject);
            $fields = $this->getConfiguration()->getFieldsForClass($class);

            $registry = DocumentFetchCreatorRegistry::singleton();
            $fetcher = $registry->getFetcher($class, time());
            if (!$fetcher) {
                continue;
            }
            $chunker = DocumentChunkFetcher::create($fetcher);
            foreach ($fields as $field) {
                $dbField = $document->toDBField($field);
                if ($dbField instanceof RelationList || $dbField instanceof UnsavedRelationList) {
                    /* @var RelationList $dbField */
                    $relatedObj = Injector::inst()->get($dbField->dataClass());
                    if (!$relatedObj instanceof $ownedDataObject) {
                        continue;
                    }
                    // Now that we know a record of this type could possibly own this one,
                    // we can fetch.
                    /* @var DataObjectDocument $candidateDocument */
                    foreach ($chunker->chunk(100) as $candidateDocument) {
                        $list = $candidateDocument->toDBField($field);
                        // Singleton returns a list, but record doesn't. Conceivable, but rare.
                        if (!$list || !$list instanceof RelationList) {
                            continue;
                        }
                        // Now test if this record actually appears in the list.
                        if ($list->filter('ID', $ownedDataObject->ID)->exists()) {
                            yield $candidateDocument;
                        }

                    }
                } else if ($dbField instanceof DataObject) {
                    $objectClass = get_class($dbField);
                    if (!$ownedDataObject instanceof $objectClass) {
                        continue;
                    }
                    // Now that we have a static confirmation, test each record.
                    /* @var DataObjectDocument $candidateDocument */
                    foreach ($chunker->chunk(100) as $candidateDocument) {
                        $relatedObj = $candidateDocument->toDBField($field);
                        // Singleton returned a dataobject, but this record did not. Rare, but possible.
                        if (!$relatedObj instanceof $objectClass) {
                            continue;
                        }
                        if ($relatedObj->ID == $ownedDataObject->ID) {
                            yield $document;
                        }
                    }
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

    public function serialize(): ?string
    {
        return serialize([
            'className' => $this->getDataObject()->baseClass(),
            'id' => $this->getDataObject()->ID,
        ]);
    }

    /**
     * @param string $serialized
     * @throws Exception
     */
    public function unserialize($serialized): void
    {
        $data = unserialize($serialized);
        $dataObject = DataObject::get_by_id($data['className'], $data['id']);
        if (!$dataObject) {
            throw new Exception(sprintf('DataObject %s : %s does not exist', $data['className'], $data['id']));
        }
        $this->setDataObject($dataObject);
        foreach (static::config()->get('dependencies') as $name => $service) {
            $method = 'set' . $name;
            $this->$method(Injector::inst()->get($service));
        }
    }

}
