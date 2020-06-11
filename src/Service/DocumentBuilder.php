<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentMetaProvider;

class DocumentBuilder
{
    use Injectable;
    use ConfigurationAware;
    use RegistryAware;

    /**
     * DocumentBuilder constructor.
     * @param IndexConfiguration $configuration
     * @param DocumentFetchCreatorRegistry $registry
     */
    public function __construct(IndexConfiguration $configuration, DocumentFetchCreatorRegistry $registry)
    {
        $this->setConfiguration($configuration);
        $this->setRegistry($registry);
    }

    /**
     * @param DocumentInterface $document
     * @return array
     */
    public function export(DocumentInterface $document): array
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

        return $data;
    }

    /**
     * @param array $data
     * @return DocumentInterface|null
     */
    public function import(array $data): ?DocumentInterface
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
}
