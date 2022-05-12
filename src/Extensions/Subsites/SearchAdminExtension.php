<?php

namespace SilverStripe\SearchService\Extensions\Subsites;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Subsites\Model\Subsite;

class SearchAdminExtension extends Extension
{

    private ?bool $stashValue = null;

    public function updateQuery(DataQuery $query, array $data): void
    {
        if (isset($data['subsite_id']) && is_numeric($data['subsite_id'])) {
            if ($this->stashValue === null) {
                $this->stashValue = Subsite::$disable_subsite_filter;
            }

            Subsite::disable_subsite_filter(true);

            // If the DataObject has a Subsite relation, then apply a SubsiteID filter
            if (DataObject::getSchema()->hasOneComponent(Subsite::class, 'Subsite')) {
                $query->where(sprintf('SubsiteID IS NULL OR SubsiteID = %d', $data['subsite_id']));
            }
        }
    }

    public function updateDocumentList(ArrayList $list): void
    {
        if ($this->stashValue !== null) {
            Subsite::disable_subsite_filter($this->stashValue);
            $this->stashValue = null;
        }
    }

}
