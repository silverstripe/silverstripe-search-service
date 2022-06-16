<?php


namespace SilverStripe\SearchService\Jobs;

use Exception;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\Versioned\Versioned;

class RemoveDataObjectJob extends IndexJob
{

    private ?DataObjectDocument $document = null;

    private ?int $timestamp = null;

    public function __construct(?DataObjectDocument $document = null, int $timestamp = null, ?int $batchSize = null)
    {
        parent::__construct([], Indexer::METHOD_ADD, $batchSize);

        if ($document !== null) {
            // We do this so that if the Dataobject is deleted, not just unpublished, we can still act upon it
            $document->setShouldFallbackToLatestVersion();
        }

        $timestamp = $timestamp ?: DBDatetime::now()->getTimestamp();

        $this->setDocument($document);
        $this->setTimestamp($timestamp);
    }

    public function getTitle()
    {
        return sprintf(
            'Search service unpublishing document "%s" (ID: %s)',
            $this->getDocument()->getDataObject()->getTitle(),
            $this->getDocument()->getIdentifier()
        );
    }

    /**
     * @throws Exception
     */
    public function setup()
    {
        // Set the documents in setup to ensure async
        $datetime = DBField::create_field('Datetime', $this->getTimestamp());
        $archiveDate = $datetime->format($datetime->getISOFormat());
        $documents = Versioned::withVersionedMode(function () use ($archiveDate) {
            Versioned::reading_archived_date($archiveDate);

            // Go back in time to find out what the owners were before unpublish
            $dependentDocs = $this->document->getDependentDocuments();

            // refetch everything on the live stage
            Versioned::set_stage(Versioned::LIVE);

            return array_map(function (DataObjectDocument $doc) {
                return DataObjectDocument::create(
                    DataObject::get_by_id(
                        $doc->getSourceClass(),
                        $doc->getDataObject()->ID
                    )
                );
            }, $dependentDocs);
        });

        $this->setDocuments($documents);

        parent::setup();
    }

    public function getDocument(): ?DataObjectDocument
    {
        return $this->document;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    private function setDocument(?DataObjectDocument $document): void
    {
        $this->document = $document;
    }

    private function setTimestamp(?int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
