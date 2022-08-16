<?php

namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBDateExtension extends Extension
{

    public function getSearchValue(): ?string
    {
        return $this->owner->Rfc3339();
    }

}
