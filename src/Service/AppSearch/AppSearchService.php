<?php

namespace SilverStripe\SearchService\Services\AppSearch;

use Elastic\AppSearch\Client\Client;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use InvalidArgumentException;
use Exception;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\IndexConfiguration;

class AppSearchService implements IndexingInterface
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
     * AppSearchService constructor.
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

            $fields = $this->getBuilder()->toArray($item);

            $documentMaxSize = $this->getMaxDocumentSize();
            if ($documentMaxSize  && strlen(json_encode($fields)) >= $documentMaxSize) {
                throw new IndexingServiceException(sprintf(
                    'Document title: %s, Document class: %s, Document ID %s: exceeds maximum allowed document size of %s bytes',
                    $fields['title'],
                    $fields['source_class'],
                    $fields['record_id'],
                    $documentMaxSize
                ), E_USER_WARNING);
                continue;
            }

            $indexes = $this->getConfiguration()->getIndexesForDocument($item);
            foreach (array_keys($indexes) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }
                $documentMap[$indexName][] = $fields;
            }
        }

        foreach ($documentMap as $indexName => $docsToAdd) {
            $result = $this->getClient()->indexDocuments(
                static::environmentizeIndex($indexName),
                $docsToAdd
            );
            $this->handleError($result);
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
     * @return int
     */
    public function getMaxDocumentSize(): int
    {
        return $this->getConfiguration()->get('max_document_size');
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
                'Invalid field name: %s. Must contain only alphanumeric characters and underscores.',
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
     * @return AppSearchService
     */
    public function setClient(Client $client): AppSearchService
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
     * @return AppSearchService
     */
    public function setBuilder(DocumentBuilder $builder): AppSearchService
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
            'AppSearch API error: %s',
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
}
