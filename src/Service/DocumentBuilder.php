<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentMetaProvider;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\Traits\RegistryAware;
use SilverStripe\SearchService\Service\Traits\ServiceAware;

class DocumentBuilder
{
    use Injectable;
    use ConfigurationAware;
    use RegistryAware;
    use ServiceAware;

    /**
     * DocumentBuilder constructor.
     * @param IndexConfiguration $configuration
     * @param DocumentFetchCreatorRegistry $registry
     * @param IndexingInterface $service
     */
    public function __construct(
        IndexConfiguration $configuration,
        DocumentFetchCreatorRegistry $registry,
        IndexingInterface $service
    )
    {
        $this->setConfiguration($configuration);
        $this->setRegistry($registry);
        $this->setIndexService($service);
    }

    /**
     * @param DocumentInterface $document
     * @return array
     * @throws IndexingServiceException
     */
    public function toArray(DocumentInterface $document): array
    {
        $idField = $this->getConfiguration()->getIDField();
        $sourceClassField = $this->getConfiguration()->getSourceClassField();

        $data = $document->toArray();
        $data[$idField] = $document->getIdentifier();

        if ($document instanceof DocumentMetaProvider) {
            $extraMeta = $document->provideMeta();
            $data = array_merge($data, $extraMeta);
        }

        $data[$sourceClassField] = $document->getSourceClass();
        $this->truncateDocument($data);

        return $data;
    }

    /**
     * @param array $data
     * @return DocumentInterface|null
     */
    public function fromArray(array $data): ?DocumentInterface
    {
        $sourceClassField = $this->getConfiguration()->getSourceClassField();
        $sourceClass = $data[$sourceClassField] ?? null;

        if (!$sourceClass) {
            return null;
        }

        $fetcher = $this->getRegistry()->getFetcher($sourceClass);

        if (!$fetcher) {
            return null;
        }

        return $fetcher->createDocument($data);
    }

    /**
     * @param array $data
     * @throws IndexingServiceException
     */
    private function truncateDocument(array $data): array
    {
        $documentMaxSize = $this->getIndexService()->getMaxDocumentSize();
        if ($documentMaxSize  && strlen(json_encode($data)) >= $documentMaxSize) {
            // truncate the document here
        }

        return $data;
    }
}
