<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\InjectorNotFoundException;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;

class DocumentFetcherRegistry
{
    use Injectable;

    /**
     * @var DocumentFetcherInterface[]
     */
    private $fetchers = [];

    /**
     * DocumentFetcherRegistry constructor.
     * @param array $fetchers
     */
    public function __construct(...$fetchers)
    {
        foreach($fetchers as $fetcher) {
            $this->addFetcher($fetcher);
        }
    }

    /**
     * @param DocumentFetcherInterface $fetcher
     * @return $this
     */
    public function addFetcher(DocumentFetcherInterface $fetcher): self
    {
        $this->fetchers[] = $fetcher;

        return $this;
    }

    /**
     * @param DocumentFetcherInterface $fetcher
     * @return $this
     */
    public function removeFetcher(DocumentFetcherInterface $fetcher): self
    {
        $class = get_class($fetcher);
        $this->fetchers = array_filter($this->fetchers, function ($fetcher) use ($class) {
            return !$fetcher instanceof $class;
        });

        return $this;
    }

    /**
     * @param string $class
     * @return DocumentFetcherInterface|null
     */
    public function getFetcherForType(string $class): ?DocumentFetcherInterface
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->appliesTo($class)) {
                return $fetcher;
            }
        }

        return null;
    }
}
