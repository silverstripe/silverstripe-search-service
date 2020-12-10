<?php


namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;

class DBHTMLFieldExtension extends Extension
{
    use ConfigurationAware;

    /**
     * @var array
     */
    private static $dependencies = [
        'Configuration' => '%$' . IndexConfiguration::class,
    ];

    /**
     * For HTML fields, we have to call ->forTemplate() so that shortcodes get processed
     *
     * @return string|string[]|null
     */
    public function getSearchValue()
    {
        if ($this->getConfiguration()->shouldIncludePageHTML()) {
            return $this->owner->forTemplate();
        }
        return preg_replace('/\s+/S', " ", strip_tags($this->owner->forTemplate()));
    }
}
