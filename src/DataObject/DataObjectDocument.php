<?php


namespace SilverStripe\SearchService\DataObject;


use Psr\Log\LoggerInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Map;
use SilverStripe\ORM\RelationList;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\ConfigurationAware;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\PageCrawler;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ViewableData;

class DataObjectDocument implements DocumentInterface
{
    use Injectable;
    use Extensible;
    use ConfigurationAware;

    /**
     * @var DataObject&SearchServiceExtension
     */
    private $dataObject;

    /**
     * @var SearchServiceInterface
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
        'Service' => '%$' . SearchServiceInterface::class,
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
     * Generates a map of all the fields and values which will be sent.
     * @return array
     */
    public function toArray(): array
    {
        $item = $this->getDataObject();
        $toIndex = [
            'objectSilverstripeID' => $item->ID,
            'objectType' => $item->baseClass(),
        ];

        if ($this->getPageCrawler() && $this->config()->get('include_page_content')) {
            $toIndex['objectForTemplate'] = $this->getPageCrawler()->getMainContent($item);
        }

        $item->invokeWithExtensions('onBeforeAttributesFromObject');

        $attributes = new Map(ArrayList::create());

        foreach ($toIndex as $k => $v) {
            $this->getService()->validateField($k);
            $attributes->push($k, $v);
        }

        $specs = $item->config()->get('search_index_fields');

        if ($specs) {
            foreach ($specs as $attributeName) {
                if (in_array($attributeName, $this->config()->get('attributes_blacklisted'))) {
                    continue;
                }

                $dbField = $item->relObject($attributeName);

                if ($dbField && ($dbField->exists() || $dbField instanceof DBBoolean)) {
                    if ($dbField instanceof RelationList || $dbField instanceof DataObject) {
                        // has-many, many-many, has-one
                        $this->exportAttributesFromRelationship($attributeName, $attributes);
                    } else {
                        // db-field, if it's a date then use the timestamp since we need it
                        switch (get_class($dbField)) {
                            case DBDate::class:
                            case DBDatetime::class:
                                $value = $dbField->getTimestamp();
                                break;
                            case DBBoolean::class:
                                $value = $dbField->getValue();
                                break;
                            default:
                                $value = $dbField->forTemplate();
                        }

                        $attributes->push($attributeName, $value);
                    }
                }
            }
        }
        // DataObject specific customisation
        $item->invokeWithExtensions('updateSearchAttributes', $attributes);

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

            if (is_iterable($related)) {
                foreach ($related as $relatedObj) {
                    $relationshipAttributes = new Map(ArrayList::create());
                    $relationshipAttributes->push('objectID', $relatedObj->ID);
                    $relationshipAttributes->push('objectTitle', $relatedObj->Title);

                    if ($item->hasMethod('updateSearchRelationshipAttributes')) {
                        $item->updateSearchRelationshipAttributes($relationshipAttributes, $relatedObj);
                    }

                    $data[] = $relationshipAttributes->toArray();
                }
            } else {
                $relationshipAttributes = new Map(ArrayList::create());
                $relationshipAttributes->push('objectID', $related->ID);
                $relationshipAttributes->push('Title', $related->Title);

                if ($item->hasMethod('updateSearchRelationshipAttributes')) {
                    $item->updateSearchRelationshipAttributes($relationshipAttributes, $related);
                }

                $data = $relationshipAttributes->toArray();
            }

            $attributes->push($this->formatField($relationship), $data);
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);
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
     * @return SearchServiceInterface
     */
    public function getService(): SearchServiceInterface
    {
        return $this->service;
    }

    /**
     * @param SearchServiceInterface $service
     * @return DataObjectDocument
     */
    public function setService(SearchServiceInterface $service): DataObjectDocument
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

    /**
     * @param string $field
     * @return string
     */
    private function formatField(string $field): string
    {
        return $this->getService()->normaliseField($field);
    }

}
