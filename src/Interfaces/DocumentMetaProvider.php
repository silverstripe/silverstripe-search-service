<?php


namespace SilverStripe\SearchService\Interfaces;


interface DocumentMetaProvider
{
    /**
     * @return array
     */
    public function provideMeta(): array;
}
