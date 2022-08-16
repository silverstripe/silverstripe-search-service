<?php

namespace SilverStripe\SearchService\Interfaces;

interface DocumentAddHandler
{

    public const BEFORE_ADD = 'before';

    public const AFTER_ADD = 'after';

    public function onAddToSearchIndexes(string $event): void;

}
