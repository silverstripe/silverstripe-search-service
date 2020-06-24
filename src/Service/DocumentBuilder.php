<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentMetaProvider;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\Traits\RegistryAware;

class DocumentBuilder
{
    use Injectable;
    use ConfigurationAware;
    use RegistryAware;

    /**
     * DocumentBuilder constructor.
     * @param IndexConfiguration $configuration
     * @param DocumentFetchCreatorRegistry $registry
     * @param IndexingInterface $service
     */
    public function __construct(
        IndexConfiguration $configuration,
        DocumentFetchCreatorRegistry $registry
    )
    {
        $this->setConfiguration($configuration);
        $this->setRegistry($registry);
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
        $data = $this->truncateDocument($data);

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
        $indexService = Injector::inst()->get(IndexingInterface::class);
        $documentMaxSize = $indexService->getMaxDocumentSize();

        if ($documentMaxSize  && strlen(json_encode($data)) >= $documentMaxSize) {
            while (strlen(json_encode($data)) >= $documentMaxSize) {
                // Sort the array so the longest value is always on top.
                uasort($data, function($a, $b) {
                    return strlen(json_encode($b)) - strlen(json_encode($a));
                });

                $item = array_slice($data, 0, 1, true);
                $data[key($item)] = substr(current($item), 0, -(strlen(current($item)) / 2));
            }
        }

        return $data;
    }
}
