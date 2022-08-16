<?php

namespace SilverStripe\SearchService\Extensions;

use SilverStripe\Core\Extension;

class DBBooleanExtension extends Extension
{

    public function getSearchValue(): ?bool
    {
        return $this->owner->getValue();
    }

}
