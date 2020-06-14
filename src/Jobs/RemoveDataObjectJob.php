<?php


namespace SilverStripe\SearchService\Jobs;


use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Service\Indexer;
use SilverStripe\Versioned\Versioned;
use Exception;

/**
 * Class RemoveDataObjectJob
 * @package SilverStripe\SearchService\Jobs
 *
 * @property DataObjectDocument $document
 * @property int $timestamp
 */
class RemoveDataObjectJob extends IndexJob
{

    /**
     * @param DataObjectDocument|null $document
     * @param int|null $timestamp
     * @param int|null $batchSize
     */
    public function __construct(?DataObjectDocument $document = null, int $timestamp = null, ?int $batchSize = null)
    {
        parent::__construct([], Indexer::METHOD_ADD, $batchSize);
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
        $datetime = DBField::create_field('Datetime', $this->timestamp);
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
                    ));
            }, $dependentDocs);
        });

        $this->indexer->setDocuments($documents);

        parent::setup();
    }
}
