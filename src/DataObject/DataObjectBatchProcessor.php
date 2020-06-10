<?php
namespace SilverStripe\SearchService\DataObject;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Jobs\IndexJob;
use SilverStripe\SearchService\Jobs\RemoveDataObjectJob;
use SilverStripe\SearchService\Service\BatchProcessor;

class DataObjectBatchProcessor extends BatchProcessor
{
    use Configurable;

    /**
     * @var int
     * @config
     */
    private static $buffer_seconds = 5;

    /**
     * @param DocumentInterface[] $documents
     * @return BatchDocumentInterface
     * @throws ValidationException
     */
    public function removeDocuments(array $documents): BatchDocumentInterface
    {
        $timestamp = time() - $this->config()->get('buffer_seconds');

        // Remove the dataobjects, ignore dependencies
        $job = IndexJob::create($documents, IndexJob::METHOD_DELETE);
        $job->setProcessDependencies(false);
        $this->run($job);
        foreach ($documents as $doc) {
            $childJob = RemoveDataObjectJob::create($doc, $timestamp);
            $this->run($childJob);
        }

        return $this;

    }
}
