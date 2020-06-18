<?php


namespace SilverStripe\SearchService\Service\Traits;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Interfaces\IndexingInterface;

trait ServiceAware
{
    /**
     * @var IndexingInterface
     */
    private $indexService;

    /**
     * @return IndexingInterface
     */
    public function getIndexService(): IndexingInterface
    {
        return $this->indexService;
    }

    /**
     * @param IndexingInterface $indexService
     * @return $this
     */
    public function setIndexService(IndexingInterface $indexService): self
    {
        $this->indexService = $indexService;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasIndexService(): bool
    {
        return Injector::inst()->has(IndexingInterface::class);
    }
}
