<?php

namespace SilverStripe\SearchService\Extensions\Subsites;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Subsites\Model\Subsite;

class SearchAdminExtension extends Extension
{
    /**
     * @var bool|null
     */
    private $stashValue;

    public function updateQuery(DataQuery $query, array $data): void
    {
        if (isset($data['subsite_id']) && is_numeric($data['subsite_id'])) {
            if ($this->stashValue === null) {
                $this->stashValue = Subsite::$disable_subsite_filter;
            }
            Subsite::disable_subsite_filter(true);
            $query->where("SubsiteID = {$data['subsite_id']}");
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
