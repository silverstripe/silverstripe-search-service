<?php

namespace SilverStripe\SearchService\Interfaces;

interface DocumentInterface
{

    public function getIdentifier(): string;

    public function shouldIndex(): bool;

    /**
     * Mark the item as indexed in search
     */
    public function markIndexed(): void;

    public function toArray(): array;

    public function getSourceClass(): string;

}
