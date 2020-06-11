<?php


namespace SilverStripe\SearchService\Interfaces;


interface DocumentInterface
{
    /**
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * @return bool
     */
    public function shouldIndex(): bool;

    /**
     * Mark the item as indexed in search
     */
    public function markIndexed(): void;

    /**
     * @return array
     */
    public function getIndexes(): array;

    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @return string
     */
    public function getSourceClass(): string;
}
