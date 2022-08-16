<?php

namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentFetchCreatorInterface;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;

class DocumentFetchCreatorRegistry
{

    use Injectable;

    /**
     * @var DocumentFetchCreatorInterface[]
     */
    private array $fetchCreators = [];

    public function __construct(...$fetchCreators) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        foreach ($fetchCreators as $creator) {
            $this->addFetchCreator($creator);
        }
    }

    public function addFetchCreator(DocumentFetchCreatorInterface $creator): self
    {
        $this->fetchCreators[] = $creator;

        return $this;
    }

    public function removeFetchCreator(DocumentFetchCreatorInterface $creator): self
    {
        $class = $creator::class;
        $this->fetchCreators = array_filter($this->fetchCreators, function ($creator) use ($class) {
            return !$creator instanceof $class;
        });

        return $this;
    }

    public function getFetcher(string $class): ?DocumentFetcherInterface
    {
        foreach ($this->fetchCreators as $creator) {
            if ($creator->appliesTo($class)) {
                return $creator->createFetcher($class);
            }
        }

        return null;
    }

}
