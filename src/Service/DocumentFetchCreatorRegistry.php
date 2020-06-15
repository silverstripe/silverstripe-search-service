<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\InjectorNotFoundException;
use SilverStripe\SearchService\Interfaces\DocumentFetchCreatorInterface;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;

class DocumentFetchCreatorRegistry
{
    use Injectable;

    /**
     * @var DocumentFetchCreatorInterface[]
     */
    private $fetchCreators = [];

    /**
     * DocumentFetchCreatorRegistry constructor.
     * @param array $fetchCreators
     */
    public function __construct(...$fetchCreators)
    {
        foreach ($fetchCreators as $creator) {
            $this->addFetchCreator($creator);
        }
    }

    /**
     * @param DocumentFetchCreatorInterface $creator
     * @return $this
     */
    public function addFetchCreator(DocumentFetchCreatorInterface $creator): self
    {
        $this->fetchCreators[] = $creator;

        return $this;
    }

    /**
     * @param DocumentFetchCreatorInterface $creator
     * @return $this
     */
    public function removeFetchCreator(DocumentFetchCreatorInterface $creator): self
    {
        $class = get_class($creator);
        $this->fetchCreators = array_filter($this->fetchCreators, function ($creator) use ($class) {
            return !$creator instanceof $class;
        });

        return $this;
    }

    /**
     * @param string $class
     * @param int|null $until
     * @return DocumentFetchCreatorInterface|null
     */
    public function getFetcher(string $class, ?int $until = null): ?DocumentFetcherInterface
    {
        foreach ($this->fetchCreators as $creator) {
            if ($creator->appliesTo($class)) {
                return $creator->createFetcher($class, $until);
            }
        }

        return null;
    }
}
