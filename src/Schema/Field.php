<?php

namespace SilverStripe\SearchService\Schema;

class Field
{
    /**
     * @var string
     */
    private $searchFieldName;

    /**
     * @var string|null
     */
    private $property;

    /**
     * @var array
     */
    private $options = [];

    /**
     * Field constructor.
     * @param string $searchFieldName
     * @param string|null $property
     * @param array $options
     */
    public function __construct(string $searchFieldName, ?string $property = null, array $options = [])
    {
        $this->searchFieldName = $searchFieldName;
        $this->property = $property;
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getSearchFieldName(): string
    {
        return $this->searchFieldName;
    }

    /**
     * @param string $searchFieldName
     * @return Field
     */
    public function setSearchFieldName(string $searchFieldName): Field
    {
        $this->searchFieldName = $searchFieldName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getProperty(): ?string
    {
        return $this->property;
    }

    /**
     * @param string|null $property
     * @return Field
     */
    public function setProperty(?string $property): Field
    {
        $this->property = $property;
        return $this;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getOption(string $key)
    {
        return $this->options[$key] ?? null;
    }

    /**
     * @param string $key
     * @param $value
     * @return Field
     */
    public function setOption(string $key, $value): Field
    {
        $this->options[$key] = $value;
        return $this;
    }


}
