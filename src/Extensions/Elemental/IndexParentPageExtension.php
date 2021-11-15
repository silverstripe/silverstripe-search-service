<?php

namespace SilverStripe\SearchService\Extensions\Elemental;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\Versioned\Versioned;

class IndexParentPageExtension extends Extension
{

    /**
     * Force a re-index of the parent page for any given element
     * @param Versioned $original
     */
    public function onAfterPublish(&$original)
    {
        if (Config::inst()->get(IndexConfiguration::class, 'index_parent_page_of_elements') === true) {
            /** @var DataObject $parent */
            $parent = $this->getOwner()->getPage();
            if ($parent && $parent->hasExtension(SearchServiceExtension::class)) {
                $parent->addToIndexes();
            }
        }
    }
}
