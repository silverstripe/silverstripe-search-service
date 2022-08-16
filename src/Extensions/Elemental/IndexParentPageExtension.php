<?php

namespace SilverStripe\SearchService\Extensions\Elemental;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Service\IndexConfiguration;

/**
 * Extension class that hooks into BaseElement to ensure that the parent page is indexed whenever an element is
 * published. This is necessary because Silverstripe CMS optimises away database write calls unless they are necessary,
 * so even when you click 'Save' or 'Publish' on a page, the page won't be saved or published unless a direct db field
 * on the page is changed.
 */
class IndexParentPageExtension extends Extension
{

    /**
     * Force a re-index of the parent page for any given element
     */
    public function onAfterPublish(): void
    {
        if (!Config::inst()->get(IndexConfiguration::class, 'index_parent_page_of_elements')) {
            return;
        }

        /** @var DataObject $parent */
        $parent = $this->getOwner()->getPage();

        if ($parent && $parent->hasExtension(SearchServiceExtension::class)) {
            $parent->addToIndexes();
        }
    }

}
