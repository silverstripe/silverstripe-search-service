<?php

namespace SilverStripe\SearchService\Interfaces;

use SilverStripe\ORM\DataObject;

/**
 * The contract to indicate that the Elastic Document sources its data from Silverstripe DataObject class
 */
interface DataObjectDocumentInterface
{

    public function getDataObject(): DataObject;

}
