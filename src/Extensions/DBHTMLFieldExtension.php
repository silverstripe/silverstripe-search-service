<?php

namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBHTMLFieldExtension extends Extension
{

    /**
     * For HTML fields, we have to call ->forTemplate() so that shortcodes get processed
     *
     * @return string|array|null
     */
    public function getSearchValue() // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if (SearchServiceExtension::singleton()->getConfiguration()->shouldIncludePageHTML()) {
            return $this->owner->forTemplate();
        }

        $value = $this->owner->forTemplate() ?? '';

        return preg_replace('/\s+/S', ' ', strip_tags($value));
    }

}
