<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\SearchService\Interfaces\SearchServiceInterface;

trait ServiceAware
{
    /**
     * @var SearchServiceInterface
     */
    private $searchService;

    /**
     * @return SearchServiceInterface
     */
    public function getSearchService(): SearchServiceInterface
    {
        return $this->searchService;
    }

    /**
     * @param SearchServiceInterface $searchService
     * @return $this
     */
    public function setSearchService(SearchServiceInterface $searchService): self
    {
        $this->searchService = $searchService;
        return $this;
    }


}
