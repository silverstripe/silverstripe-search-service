<?php


namespace SilverStripe\SearchService\Jobs;


use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\Versioned\Versioned;
use DateTime;
use Exception;

/**
 * Class RemoveDataObjectJob
 * @package SilverStripe\SearchService\Jobs
 *
 * @property DocumentInterface $document
 * @property int $timestamp
 */
class RemoveDataObjectJob extends IndexJob
{

    /**
     * @param DataObjectDocument $document
     * @param int|null $timestamp
     * @param int|null $batchSize
     */
    public function __construct(DataObjectDocument $document, int $timestamp = null, ?int $batchSize = null)
    {
        parent::__construct([], static::METHOD_ADD, $batchSize);
        $this->timestamp = $timestamp ?: time();
        $this->document = $document;
    }

    /**
     * Defines the title of the job.
     *
     * @return string
     */
    public function getTitle()
    {
        return sprintf(
            'Search service unpublishing document "%s" (ID: %s)',
            $this->document->getDataObject()->getTitle(),
            $this->document->getIdentifier()
        );
    }

    /**
     * @throws Exception
     */
    public function setup()
    {
        // Set the documents in setup to ensure async
        $datetime = new DateTime($this->timestamp);
        $archiveDate = $datetime->format('Y-m-d H:i:s');
        Versioned::withVersionedMode(function () use ($archiveDate) {
            Versioned::reading_archived_date($archiveDate);
            $this->documents = $this->document->getDependentDocuments();
        });
        parent::setup();
    }
}
