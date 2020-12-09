<?php


namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBHTMLFieldExtension extends Extension
{
    /**
     * For HTML fields, we have to call ->forTemplate() so that shortcode get processed
     *
     * @param bool $shouldIncludeHTML
     * @return string|string[]|null
     */
    public function getSearchValue(bool $shouldIncludeHTML = true)
    {
        if ($shouldIncludeHTML) {
            return $this->owner->forTemplate();
        }
        return preg_replace('/\s+/S', " ", strip_tags($this->owner->forTemplate()));
    }
}
