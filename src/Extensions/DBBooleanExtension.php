<?php


namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBBooleanExtension extends Extension
{
    public function getSearchValue(bool $shouldIncludeHTML = true)
    {
        return $this->owner->getValue();
    }
}
