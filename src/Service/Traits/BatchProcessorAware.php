<?php

namespace SilverStripe\SearchService\Service\Traits;

use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;

trait BatchProcessorAware
{

    private ?BatchDocumentInterface $batchProcessor = null;

    public function setBatchProcessor(BatchDocumentInterface $processor): self
    {
        $this->batchProcessor = $processor;

        return $this;
    }

    public function getBatchProcessor(): BatchDocumentInterface
    {
        return $this->batchProcessor;
    }

}
