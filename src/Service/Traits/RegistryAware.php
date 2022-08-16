<?php

namespace SilverStripe\SearchService\Service\Traits;

use SilverStripe\SearchService\Service\DocumentFetchCreatorRegistry;

trait RegistryAware
{

    private ?DocumentFetchCreatorRegistry $registry = null;

    public function getRegistry(): DocumentFetchCreatorRegistry
    {
        return $this->registry;
    }

    public function setRegistry(DocumentFetchCreatorRegistry $registry): self
    {
        $this->registry = $registry;

        return $this;
    }

}
