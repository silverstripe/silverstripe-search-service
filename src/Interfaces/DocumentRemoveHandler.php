<?php


namespace SilverStripe\SearchService\Interfaces;


interface DocumentRemoveHandler
{
    const BEFORE_REMOVE = 'before';

    const AFTER_REMOVE = 'after';

    /**
     * @param string $event
     */
    public function onRemoveFromSearchIndexes(string $event): void;
}
