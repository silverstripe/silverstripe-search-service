<?php


namespace SilverStripe\SearchService\Service;


use Psr\Log\LoggerInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Map;
use SilverStripe\ORM\RelationList;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\View\ViewableData;
use Exception;

class DocumentBuilder
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
     * DocumentBuilder constructor.
     * @param DataObject $object
     */
    public function __construct(DataObject $object)
    {
        $this->setDataObject($object);
    }

    /**
     * Generates a map of all the fields and values which will be sent.
     * @return Map
     */
    public function exportAttributes(): Map
    {
        $item = $this->getDataObject();
        $toIndex = [
            'objectSilverstripeID' => $item->ID,
            'objectTitle' => (string) $item->Title,
            'objectClassName' => get_class($item),
            'objectType' => $item->baseClass(),
            'objectClassNameHierarchy' => array_values(ClassInfo::ancestry(get_class($item))),
            'objectLastEdited' => $item->dbObject('LastEdited')->getTimestamp(),
            'objectCreated' => $item->dbObject('Created')->getTimestamp(),
            'objectLink' => str_replace(['?stage=Stage', '?stage=Live'], '', $item->AbsoluteLink())
        ];

        if ($this->getPageCrawler() && $this->config()->get('include_page_content')) {
            $toIndex['objectForTemplate'] = $this->getPageCrawler()->getMainContent($item);
        }

        $item->invokeWithExtensions('onBeforeAttributesFromObject');

        $attributes = new Map(ArrayList::create());

        foreach ($toIndex as $k => $v) {
            $attributes->push($this->formatField($k), $v);
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

        return $attributes;
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
                    $relationshipAttributes->push($this->formatField('objectID'), $relatedObj->ID);
                    $relationshipAttributes->push($this->formatField('objectTitle'), $relatedObj->Title);

                    if ($item->hasMethod('updateSearchRelationshipAttributes')) {
                        $item->updateSearchRelationshipAttributes($relationshipAttributes, $relatedObj);
                    }

                    $data[] = $relationshipAttributes->toArray();
                }
            } else {
                $relationshipAttributes = new Map(ArrayList::create());
                $relationshipAttributes->push($this->formatField('objectID'), $related->ID);
                $relationshipAttributes->push($this->formatField('Title'), $related->Title);

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
     * @return DocumentBuilder
     */
    public function setDataObject(DataObject $dataObject): DocumentBuilder
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
     * @return DocumentBuilder
     */
    public function setFieldFormatter(?callable $fieldFormatter): DocumentBuilder
    {
        $this->fieldFormatter = $fieldFormatter;
        return $this;
    }


}
