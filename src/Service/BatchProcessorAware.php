<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;

trait BatchProcessorAware
{
    /**
     * @var BatchDocumentInterface
     */
    private $batchProcessor;

    /**
     * @param BatchDocumentInterface $processor
     * @return $this
     */
    public function setBatchProcessor(BatchDocumentInterface $processor): self
    {
        $this->batchProcessor = $processor;

        return $this;
    }

    /**
     * @return BatchDocumentInterface
     */
    public function getBatchProcessor(): BatchDocumentInterface
    {
        return $this->batchProcessor;
    }

}
