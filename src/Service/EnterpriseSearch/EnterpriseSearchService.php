<?php

namespace SilverStripe\SearchService\Service\EnterpriseSearch;

use Elastic\EnterpriseSearch\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use InvalidArgumentException;
use Exception;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\IndexConfiguration;

class EnterpriseSearchService implements IndexingInterface, BatchDocumentRemovalInterface
{
    use Configurable;
    use ConfigurationAware;
    use Injectable;

    const DEFAULT_FIELD_TYPE = 'text';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var DocumentBuilder
     */
    private $builder;

    /**
     * @var int
     * @config
     */
    private static $max_document_size = 102400;

    /**
     * EnterpriseSearchService constructor.
     * @param Client $client
     * @param IndexConfiguration $configuration
     * @param DocumentBuilder $exporter
     */
    public function __construct(Client $client, IndexConfiguration $configuration, DocumentBuilder $exporter)
    {
        $this->client = $client;
        $this->setConfiguration($configuration);
        $this->setBuilder($exporter);
    }

    /**
     * @param DocumentInterface $item
     * @return IndexingInterface
     * @throws IndexingServiceException
     */
    public function addDocument(DocumentInterface $item): IndexingInterface
    {
        $this->addDocuments([$item]);

        return $this;
    }

    /**
     * @param DocumentInterface[] $items
     * @return BatchDocumentInterface
     * @throws IndexingServiceException
     */
    public function addDocuments(array $items): BatchDocumentInterface
    {
        $documentMap = [];
        /* @var DocumentInterface $item */
        foreach ($items as $item) {
            if (!$item instanceof DocumentInterface) {
                throw new InvalidArgumentException(sprintf(
                    '%s not passed an instance of %s',
                    __FUNCTION__,
                    DocumentInterface::class
                ));
            }
            if (!$item->shouldIndex()) {
                continue;
            }

            try {
                $fields = $this->getBuilder()->toArray($item);
            } catch (IndexConfigurationException $e) {
                Injector::inst()->get(LoggerInterface::class)->warning(
                    sprintf("Failed to convert document to array: %s", $e->getMessage())
                );
                continue;
            }

            $indexes = $this->getConfiguration()->getIndexesForDocument($item);

            if (empty($indexes)) {
                Injector::inst()->get(LoggerInterface::class)->warn(
                    sprintf("No valid indexes found for document %s, skipping...", $item->getIdentifier())
                );
                continue;
            }

            foreach (array_keys($indexes) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }
                $documentMap[$indexName][] = $fields;
            }
        }

        foreach ($documentMap as $indexName => $docsToAdd) {
            try {
                $result = $this->getClient()->indexDocuments(
                    static::environmentizeIndex($indexName),
                    $docsToAdd
                );
                $this->handleError($result);
            } catch (Exception $e) {
                Injector::inst()->get(LoggerInterface::class)->error(
                    sprintf("Failed to index documents: %s", $e->getMessage())
                );
                continue;
            }
        }
        return $this;
    }

    /**
     * @param DocumentInterface $doc
     * @return IndexingInterface
     * @throws Exception
     */
    public function removeDocument(DocumentInterface $doc): IndexingInterface
    {
        $this->removeDocuments([$doc]);

        return $this;
    }

    /**
     * @param DocumentInterface[] $items
     * @return BatchDocumentInterface
     * @throws Exception
     */
    public function removeDocuments(array $items): BatchDocumentInterface
    {
        $documentMap = [];
        /* @var DocumentInterface $item */
        foreach ($items as $item) {
            if (!$item instanceof DocumentInterface) {
                throw new InvalidArgumentException(sprintf(
                    '%s not passed an instance of %s',
                    __FUNCTION__,
                    DocumentInterface::class
                ));
            }
            $indexes = $this->getConfiguration()->getIndexesForDocument($item);
            foreach (array_keys($indexes) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }
                $documentMap[$indexName][] = $item->getIdentifier();
            }
        }
        foreach ($documentMap as $indexName => $idsToRemove) {
            $result = $this->getClient()->deleteDocuments(
                static::environmentizeIndex($indexName),
                $idsToRemove
            );
            $this->handleError($result);
        }

        return $this;
    }

    /**
     * Forcefully remove all documents from the provided index name. Batches the requests to Elastic based upon the
     * configured batch size, beginning at page 1 and continuing until the index is empty.
     *
     * @param string $indexName The index name to remove all documents from
     * @return int The total number of documents removed
     */
    public function removeAllDocuments(string $indexName): int
    {
        $cfg = $this->getConfiguration();
        $client = $this->getClient();
        $indexName = static::environmentizeIndex($indexName);
        $numDeleted = 0;

        $documents = $client->listDocuments($indexName, 1, $cfg->getBatchSize());

        // Loop forever until we no longer get any results
        while (is_array($documents) && sizeof($documents['results']) > 0) {
            $idsToRemove = [];

            // Create the list of indexed documents to remove
            foreach ($documents['results'] as $doc) {
                $idsToRemove[] = $doc['id'];
            }

            // Actually delete the documents
            $deletedDocs = $client->deleteDocuments($indexName, $idsToRemove);

            // Keep an accurate running count of the number of documents deleted.
            foreach ($deletedDocs as $doc) {
                if (is_array($doc) && isset($doc['deleted']) && $doc['deleted'] === true) {
                    $numDeleted++;
                }
            }

            // Re-fetch $documents now that we've deleted this batch
            $documents = $client->listDocuments($indexName, 1, $cfg->getBatchSize());
        }

        return $numDeleted;
    }

    /**
     * @return int
     */
    public function getMaxDocumentSize(): int
    {
        return $this->config()->get('max_document_size');
    }

    /**
     * @param string $id
     * @return DocumentInterface|null
     * @throws IndexingServiceException
     */
    public function getDocument(string $id): ?DocumentInterface
    {
        $result = $this->getDocuments([$id]);

        return $result[0] ?? null;
    }

    /**
     * @param array $ids
     * @return DocumentInterface[]
     * @throws IndexingServiceException
     */
    public function getDocuments(array $ids): array
    {
        $docs = [];
        foreach (array_keys($this->getConfiguration()->getIndexes()) as $indexName) {
            $response = $this->getClient()->getDocuments(
                static::environmentizeIndex($indexName),
                $ids
            );
            $this->handleError($response);
            if ($response) {
                foreach ($response['results'] as $data) {
                    $document = $this->getBuilder()->fromArray($data);
                    if ($document) {
                        $docs[$document->getIdentifier()] = $document;
                    }
                }
            }
        }

        return array_values($docs);
    }

    /**
     * @param string $indexName
     * @param int|null $limit
     * @param int $offset
     * @return DocumentInterface[]
     * @throws Exception
     */
    public function listDocuments(string $indexName, ?int $limit = null, int $offset = 0): array
    {
        try {
            $response = $this->getClient()->listDocuments(
                static::environmentizeIndex($indexName),
                $offset,
                $limit
            );
            $this->handleError($response);
            if ($response) {
                $documents = [];
                foreach ($response['results'] as $data) {
                    $document = $this->getBuilder()->fromArray($data);
                    if ($document) {
                        $documents[] = $document;
                    }
                }

                return $documents;
            }
        } catch (Exception $e) {
        }

        return [];
    }

    /**
     * @param string $indexName
     * @return int
     * @throws IndexingServiceException
     */
    public function getDocumentTotal(string $indexName): int
    {

        $response = $this->getClient()->listDocuments(
            static::environmentizeIndex($indexName)
        );
        $this->handleError($response);
        $total = $response['meta']['page']['total_results'] ?? null;
        if ($total === null) {
            throw new IndexingServiceException(sprintf(
                'Total results not provided in meta content'
            ));
        }

        return $total;
    }

    /**
     * Ensure all the engines exist
     * @throws IndexingServiceException
     * @throws IndexConfigurationException
     */
    public function configure(): void
    {
        foreach ($this->getConfiguration()->getIndexes() as $indexName => $config) {
            $this->validateIndex($indexName);

            $envIndex = static::environmentizeIndex($indexName);
            $this->findOrMakeIndex($envIndex);

            $result = $this->getClient()->getSchema($envIndex);
            $this->handleError($result);

            $fields = $this->getConfiguration()->getFieldsForIndex($indexName);
            $definedSchema = $this->getSchemaForFields($fields);
            $needsUpdate = false;
            foreach ($result as $fieldName => $type) {
                $definedType = $definedSchema[$fieldName] ?? null;
                if (!$definedType) {
                    continue;
                }
                if ($definedType !== $type) {
                    $needsUpdate = true;
                    break;
                }
            }
            foreach ($definedSchema as $fieldName => $type) {
                $existingType = $result[$fieldName] ?? null;
                if (!$existingType) {
                    $needsUpdate = true;
                    break;
                }
            }
            if ($needsUpdate) {
                $response = $this->getClient()->updateSchema($envIndex, $definedSchema);
                $this->handleError($response);
            }
        }
    }


    /**
     * @param string $field
     * @throws IndexConfigurationException
     */
    public function validateField(string $field): void
    {
        if ($field[0] === '_') {
            throw new IndexConfigurationException(sprintf(
                'Invalid field name: %s. Fields cannot begin with underscores.',
                $field
            ));
        }
        if (preg_match('/[^a-z0-9_]/', $field)) {
            throw new IndexConfigurationException(sprintf(
                'Invalid field name: %s. Must contain only lowercase alphanumeric characters and underscores.',
                $field
            ));
        }
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     * @return EnterpriseSearchService
     */
    public function setClient(Client $client): EnterpriseSearchService
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return DocumentBuilder
     */
    public function getBuilder(): DocumentBuilder
    {
        return $this->builder;
    }

    /**
     * @param DocumentBuilder $builder
     * @return EnterpriseSearchService
     */
    public function setBuilder(DocumentBuilder $builder): EnterpriseSearchService
    {
        $this->builder = $builder;
        return $this;
    }


    /**
     * @param string $index
     * @throws IndexingServiceException
     */
    private function findOrMakeIndex(string $index)
    {
        $engines = $this->getClient()->listEngines();
        $this->handleError($engines);
        $results = $engines['results'] ?? [];
        $allEngines = array_column($results, 'name');
        if (!in_array($index, $allEngines)) {
            $result = $this->getClient()->createEngine($index);
            $this->handleError($result);
        }
    }

    /**
     * @param array|null $result
     * @throws IndexingServiceException
     */
    private function handleError(?array $result)
    {
        if (!is_array($result)) {
            return;
        }

        $errors = array_column($result, 'errors');
        if (empty($errors)) {
            return;
        }
        $allErrors = [];
        foreach ($errors as $errorGroup) {
            $allErrors = array_merge($allErrors, $errorGroup);
        }
        if (empty($allErrors)) {
            return;
        }
        throw new IndexingServiceException(sprintf(
            'EnterpriseSearch API error: %s',
            print_r($allErrors, true)
        ));
    }

    /**
     * @param Field[] $fields
     * @return array
     */
    private function getSchemaForFields(array $fields): array
    {
        $definedSpecs = [];
        foreach ($fields as $field) {
            $explicitFieldType = $field->getOption('type') ?? self::DEFAULT_FIELD_TYPE;
            $definedSpecs[$field->getSearchFieldName()] = $explicitFieldType;
        }

        return $definedSpecs;
    }

    /**
     * @param string $index
     * @throws IndexConfigurationException
     */
    private function validateIndex(string $index): void
    {
        $validTypes = [
            self::DEFAULT_FIELD_TYPE,
            'date',
            'number',
            'geolocation',
        ];

        $map = [];
        foreach ($this->getConfiguration()->getFieldsForIndex($index) as $field) {
            $type = $field->getOption('type') ?? self::DEFAULT_FIELD_TYPE;
            if (!in_array($type, $validTypes)) {
                throw new IndexConfigurationException(sprintf(
                    'Invalid field type: %s',
                    $type
                ));
            }
            $alreadyDefined = $map[$field->getSearchFieldName()] ?? null;
            if ($alreadyDefined && $alreadyDefined !== $type) {
                throw new IndexConfigurationException(sprintf(
                    'Field "%s" is defined twice in the same index with differing types.
                    (%s and %s). Consider changing the field name or explicitly defining
                    the type on each usage',
                    $field->getSearchFieldName(),
                    $alreadyDefined,
                    $type
                ));
            }

            $map[$field->getSearchFieldName()] = $type;
        }
    }

    /**
     * @param string $indexName
     * @return string
     */
    public static function environmentizeIndex(string $indexName): string
    {
        $variant = IndexConfiguration::singleton()->getIndexVariant();
        if ($variant) {
            return sprintf("%s-%s", $variant, $indexName);
        }

        return $indexName;
    }

    public function getExternalURL(): ?string
    {
        return Environment::getEnv('ENTERPRISE_SEARCH_ENDPOINT') ?: null;
    }

    public function getExternalURLDescription(): ?string
    {
        return 'Elastic App Search Dashboard';
    }

    public function getDocumentationURL(): ?string
    {
        return 'https://www.elastic.co/guide/en/app-search/current/guides.html';
    }
}
