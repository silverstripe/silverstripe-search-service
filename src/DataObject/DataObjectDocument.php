<?php


namespace SilverStripe\SearchService\DataObject;

use Exception;
use InvalidArgumentException;
use LogicException;
use Serializable;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\RelationList;
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Extensions\DBFieldExtension;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DependencyTracker;
use SilverStripe\SearchService\Interfaces\DocumentAddHandler;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentMetaProvider;
use SilverStripe\SearchService\Interfaces\DocumentRemoveHandler;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Service\DocumentChunkFetcher;
use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\PageCrawler;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\Traits\ServiceAware;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ViewableData;

class DataObjectDocument implements
    DocumentInterface,
    DependencyTracker,
    Serializable,
    DocumentRemoveHandler,
    DocumentAddHandler,
    DocumentMetaProvider
{
    use Injectable;
    use Extensible;
    use Configurable;
    use ConfigurationAware;
    use ServiceAware;

    /**
     * @var string
     * @config
     */
    private static $record_id_field = 'record_id';

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
     * @var PageCrawler
     */
    private $pageCrawler;

    /**
     * @var bool
     */
    private $shouldFallbackToLatestVersion = false;

    /**
     * @var array
     */
    private static $dependencies = [
        'IndexService' => '%$' . IndexingInterface::class,
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
     * @return string
     */
    public function getSourceClass(): string
    {
        return $this->getDataObject()->ClassName;
    }

    /**
     * @param bool $fallback
     * @return $this
     */
    public function setShouldFallbackToLatestVersion(bool $fallback = true): DataObjectDocument
    {
        $this->shouldFallbackToLatestVersion = $fallback;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldIndex(): bool
    {
        $dataObject = $this->getDataObject();

        // If an anonymous user can't view it
        $isPublic = Member::actAs(null, function () use ($dataObject) {
            // Need to make sure that the version of the DataObject that we access is always the LIVE version
            return Versioned::withVersionedMode(function () use ($dataObject): bool {
                Versioned::set_stage(Versioned::LIVE);

                $liveDataObject = DataObject::get($dataObject->ClassName)->byID($dataObject->ID);

                if (!$liveDataObject || !$liveDataObject->exists()) {
                    return false;
                }

                return $liveDataObject->canView();
            });
        });

        if (!$isPublic) {
            return false;
        }

        // "ShowInSearch" field
        if ($dataObject->hasField('ShowInSearch') && !$dataObject->ShowInSearch) {
            return false;
        }

        // Dataobject is only in draft
        if ($dataObject->hasExtension(Versioned::class) && !$dataObject->isLiveVersion()) {
            return false;
        }

        // Indexing is globally disabled
        if (!$this->getConfiguration()->isEnabled()) {
            return false;
        }

        if (empty($this->getConfiguration()->getIndexesForDocument($this))) {
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
     * @param bool $isDeleted
     */
    public function markIndexed(bool $isDeleted = false): void
    {
        $schema = DataObject::getSchema();
        $table = $schema->tableForField($this->getDataObject()->ClassName, 'SearchIndexed');

        if ($table) {
            $newValue = $isDeleted ? 'null' : "'" . DBDatetime::now()->Rfc2822() . "'";
            DB::query(sprintf(
                "UPDATE %s SET SearchIndexed = %s WHERE ID = %s",
                $table,
                $newValue,
                $this->getDataObject()->ID
            ));

            if ($this->getDataObject()->hasExtension(Versioned::class) && $this->getDataObject()->hasStages()) {
                DB::query(sprintf(
                    'UPDATE %s_Live SET SearchIndexed = %s WHERE ID = %s',
                    $table,
                    $newValue,
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
        $pageContentField = $this->config()->get('page_content_field');
        if ($this->getDataObject()->hasExtension(Versioned::class)) {
            $dataObject = Versioned::withVersionedMode(function () {
                Versioned::set_stage(Versioned::LIVE);
                return DataObject::get_by_id($this->getSourceClass(), $this->getDataObject()->ID);
            });
        } else {
            $dataObject = DataObject::get_by_id(
                $this->getSourceClass(),
                $this->getDataObject()->ID
            );
        }

        if (!$dataObject || !$dataObject->exists()) {
            throw new IndexConfigurationException(
                sprintf(
                    "Unable to index %s with ID %d: dataobject not found",
                    $this->getSourceClass(),
                    $this->getDataObject()->ID
                )
            );
        }

        $toIndex = [];

        if ($this->getPageCrawler() && $this->getConfiguration()->shouldCrawlPageContent()) {
            $content = $this->getPageCrawler()->getMainContent($dataObject);
            if (!$this->getConfiguration()->shouldIncludePageHTML()) {
                $content = strip_tags($content);
            }
            $toIndex[$pageContentField] = $content;
        }

        $dataObject->invokeWithExtensions('onBeforeAttributesFromObject');

        $attributes = [];

        foreach ($toIndex as $k => $v) {
            $this->getIndexService()->validateField($k);
            $attributes[$k] = $v;
        }

        foreach ($this->getIndexedFields() as $field) {
            $this->getIndexService()->validateField($field->getSearchFieldName());
            /* @var DBField&DBFieldExtension $dbField */
            $dbField = $this->getFieldValue($field);
            if (!$dbField) {
                continue;
            }
            if (is_array($dbField)) {
                if (ArrayLib::is_associative($dbField)) {
                    throw new IndexConfigurationException(sprintf(
                        'Field "%s" returns an array, but it is associative',
                        $field->getSearchFieldName()
                    ));
                }

                $validated = array_filter($dbField, 'is_scalar');

                if (sizeof($validated) !== sizeof($dbField)) {
                    throw new IndexConfigurationException(sprintf(
                        'Field "%s" returns an array, but some of its values are non scalar',
                        $field->getSearchFieldName()
                    ));
                }

                $attributes[$field->getSearchFieldName()] = $dbField;
                continue;
            } elseif ($dbField instanceof ViewableData) {
                if ($dbField instanceof RelationList || $dbField instanceof DataObject) {
                    throw new IndexConfigurationException(sprintf(
                        'Field "%s" returns a DataObject or RelationList. To index fields from relationships,
                        use the "property" node to specify dot notation for the fields you want. For instance,
                        blogTags: { property: Tags.Title }',
                        $field->getSearchFieldName()
                    ));
                } elseif ($dbField instanceof DBField) {
                    $value = $dbField->getSearchValue();
                    $attributes[$field->getSearchFieldName()] = $value;
                    continue;
                }
            }

            throw new IndexConfigurationException(sprintf(
                'Field "%s" returns value that cannot be resolved',
                $field->getSearchFieldName()
            ));
        }

        // DataObject specific customisation
        $dataObject->invokeWithExtensions('updateSearchAttributes', $attributes);

        // Universal customisation
        $this->extend('updateSearchAttributes', $attributes);

        return $attributes;
    }

    /**
     * @return array
     */
    public function provideMeta(): array
    {
        $baseClassField = $this->config()->get('base_class_field');
        $recordIDField = $this->config()->get('record_id_field');

        return [
            $baseClassField => $this->getDataObject()->baseClass(),
            $recordIDField => $this->getDataObject()->ID,
        ];
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
     * @param Field $field
     * @return ViewableData|null
     */
    public function getFieldDependency(Field $field): ?ViewableData
    {
        $tuple = $this->getFieldTuple($field);
        if ($tuple) {
            return $tuple[0];
        }

        return null;
    }

    /**
     * @param Field $field
     * @return mixed|null
     */
    public function getFieldValue(Field $field)
    {
        $tuple = $this->getFieldTuple($field);
        if ($tuple) {
            return $tuple[1];
        }

        return null;
    }

    /**
     * @return array
     */
    public function getDependentDocuments(): array
    {
        $searchableClasses = $this->getConfiguration()->getSearchableClasses();
        $dataObjectClasses = array_filter($searchableClasses, function ($class) {
            return is_subclass_of($class, DataObject::class);
        });
        $ownedDataObject = $this->getDataObject();
        $docs = [];
        foreach ($dataObjectClasses as $class) {
            // Start with a singleton to look at the model first, then get real records if needed
            $owningDataObject = Injector::inst()->get($class);

            $document = DataObjectDocument::create($owningDataObject);
            $fields = $this->getConfiguration()->getFieldsForClass($class);

            $registry = DocumentFetchCreatorRegistry::singleton();
            $fetcher = $registry->getFetcher($class);
            if (!$fetcher) {
                continue;
            }
            $chunker = DocumentChunkFetcher::create($fetcher);
            foreach ($fields as $field) {
                $dependency = $document->getFieldDependency($field);
                if (!$dependency) {
                    continue;
                }
                if ($dependency instanceof RelationList || $dependency instanceof UnsavedRelationList) {
                    /* @var RelationList $dbField */
                    $relatedObj = Injector::inst()->get($dependency->dataClass());
                    if (!$relatedObj instanceof $ownedDataObject) {
                        continue;
                    }
                    // Now that we know a record of this type could possibly own this one,
                    // we can fetch.
                    /* @var DataObjectDocument $candidateDocument */
                    foreach ($chunker->chunk(100) as $candidateDocument) {
                        $list = $candidateDocument->getFieldDependency($field);
                        // Singleton returns a list, but record doesn't. Conceivable, but rare.
                        if (!$list || !$list instanceof RelationList) {
                            continue;
                        }
                        // Now test if this record actually appears in the list.
                        if ($list->filter('ID', $ownedDataObject->ID)->exists()) {
                            $docs[$candidateDocument->getIdentifier()] = $candidateDocument;
                        }
                    }
                } elseif ($dependency instanceof DataObject) {
                    $objectClass = get_class($dependency);
                    if (!$ownedDataObject instanceof $objectClass) {
                        continue;
                    }
                    // Now that we have a static confirmation, test each record.
                    /* @var DataObjectDocument $candidateDocument */
                    foreach ($chunker->chunk(100) as $candidateDocument) {
                        $relatedObj = $candidateDocument->getFieldValue($field);
                        // Singleton returned a dataobject, but this record did not. Rare, but possible.
                        if (!$relatedObj instanceof $objectClass) {
                            continue;
                        }
                        if ($relatedObj->ID == $ownedDataObject->ID) {
                            $docs[$document->getIdentifier()] = $document;
                        }
                    }
                }
            }
        }
        $dependentDocs = array_values($docs);
        $this->getDataObject()->invokeWithExtensions('updateSearchDependentDocuments', $dependentDocs);

        return $dependentDocs;
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
     * @throws InvalidArgumentException
     */
    public function setDataObject(DataObject $dataObject)
    {
        if (!$dataObject->hasExtension(SearchServiceExtension::class)) {
            throw new InvalidArgumentException(sprintf(
                'DataObject %s does not have the %s extension',
                get_class($dataObject),
                SearchServiceExtension::class
            ));
        }

        $this->dataObject = $dataObject;

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

    /**
     * @param array $path
     * @param DataObject|DataList|null $context
     * @return array
     * @throws LogicException
     */
    private function parsePath(array $path, $context = null): ?array
    {
        $subject = $context ?: $this->getDataObject();
        $nextField = array_shift($path);
        if ($subject instanceof DataObject) {
            $result = $subject->obj($nextField);
            if ($result instanceof DBField) {
                $dependency = $subject === $this->getDataObject() ? null : $subject;
                return [$dependency, $result];
            }
            return $this->parsePath($path, $result);
        }

        if ($subject instanceof DataList || $subject instanceof UnsavedRelationList) {
            if (!$nextField) {
                return [$subject, $subject];
            }
            $singleton = DataObject::singleton($subject->dataClass());
            if ($singleton->hasField($nextField)) {
                $value = $subject->column($nextField);
                return [$subject, $value];
            }

            $maybeList = $singleton->obj($nextField);
            if ($maybeList instanceof RelationList || $maybeList instanceof UnsavedRelationList) {
                return $this->parsePath($path, $subject->relation($nextField));
            }
        }

        throw new LogicException(sprintf(
            'Cannot resolve field %s on list of class %s',
            $nextField,
            $subject->dataClass()
        ));
    }

    /**
     * @param string $field
     * @return ViewableData|null
     */
    private function resolveField(string $field): ?ViewableData
    {
        $subject = $this->getDataObject();
        $result = $subject->obj($field);

        if ($result && $result instanceof DBField) {
            return $result;
        }

        $normalFields = array_merge(
            array_keys(
                DataObject::getSchema()
                    ->fieldSpecs($subject, DataObjectSchema::DB_ONLY)
            ),
            array_keys(
                $subject->hasMany()
            ),
            array_keys(
                $subject->manyMany()
            )
        );

        $lowercaseFields = array_map('strtolower', $normalFields);
        $lookup = array_combine($lowercaseFields, $normalFields);
        $fieldName = $lookup[strtolower($field)] ?? null;

        return $fieldName ? $subject->obj($fieldName) : null;
    }

    /**
     * @param Field $field
     * @return array
     */
    private function getFieldTuple(Field $field): array
    {
        if ($field->getProperty()) {
            $path = explode('.', $field->getProperty());
            return $this->parsePath($path);
        }

        return [null, $this->resolveField($field->getSearchFieldName())];
    }

    public function serialize(): ?string
    {
        return serialize([
            'className' => $this->getDataObject()->baseClass(),
            'id' => $this->getDataObject()->ID ?: $this->getDataObject()->OldID,
            'fallback' => $this->shouldFallbackToLatestVersion,
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
        if (!$dataObject && DataObject::has_extension($data['className'], Versioned::class) && $data['fallback']) {
            // get the latest version - usually this is an object that has been deleted
            $dataObject = Versioned::get_latest_version(
                $data['className'],
                $data['id']
            );
        }
        if (!$dataObject) {
            throw new Exception(sprintf('DataObject %s : %s does not exist', $data['className'], $data['id']));
        }
        $this->setDataObject($dataObject);
        foreach (static::config()->get('dependencies') as $name => $service) {
            $method = 'set' . $name;
            $this->$method(Injector::inst()->get($service));
        }
    }

    /**
     * @param string $event
     */
    public function onAddToSearchIndexes(string $event): void
    {
        if ($event === DocumentAddHandler::AFTER_ADD) {
            $this->markIndexed();
        }
    }

    /**
     * @param string $event
     */
    public function onRemoveFromSearchIndexes(string $event): void
    {
        if ($event === DocumentRemoveHandler::AFTER_REMOVE) {
            $this->markIndexed(true);
        }
    }
}
