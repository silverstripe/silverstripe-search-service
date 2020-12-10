<?php


namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBFieldExtension extends Extension
{
    public function getSearchValue()
    {
        return preg_replace('/\s+/S', " ", strip_tags($this->owner->getValue()));
    }
}
