<?php


namespace SilverStripe\SearchService\Interfaces;


interface DocumentAddHandler
{
    const BEFORE_ADD = 'before';

    const AFTER_ADD = 'after';

    /**
     * @param string $event
     * @return void
     */
    public function onAddToSearchIndexes(string $event): void;

}
