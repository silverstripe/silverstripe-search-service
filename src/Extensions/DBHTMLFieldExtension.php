<?php


namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBHTMLFieldExtension extends Extension
{
    public function getSearchValue(bool $shouldIncludeHTML = true)
    {
        if ($shouldIncludeHTML) {
            return $this->owner->forTemplate();
        }
        return preg_replace('/\s+/S', " ", strip_tags($this->owner->forTemplate()));
    }
}
