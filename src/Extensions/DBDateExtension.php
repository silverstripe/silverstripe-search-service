<?php


namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBDateExtension extends Extension
{
    public function getSearchValue(bool $shouldIncludeHTML = true)
    {
        return $this->owner->getTimestamp();
    }
}
