<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Interfaces\IndexingInterface;

trait ServiceAware
{
    /**
     * @var IndexingInterface
     */
    private $searchService;

    /**
     * @return IndexingInterface
     */
    public function getSearchService(): IndexingInterface
    {
        return $this->searchService;
    }

    /**
     * @param IndexingInterface $searchService
     * @return $this
     */
    public function setSearchService(IndexingInterface $searchService): self
    {
        $this->searchService = $searchService;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasSearchService(): bool
    {
        return Injector::inst()->has(IndexingInterface::class);
    }


}
