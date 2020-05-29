<?php


namespace SilverStripe\SearchService\Extensions;


use SilverStripe\Core\Extension;

class DBFieldExtension extends Extension
{
    public function getSearchValue()
    {
        return $this->owner->forTemplate();
    }
}
