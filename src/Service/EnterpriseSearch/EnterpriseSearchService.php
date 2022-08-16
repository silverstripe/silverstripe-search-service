<?php

namespace SilverStripe\SearchService\Service\EnterpriseSearch;

use Elastic\EnterpriseSearch\AppSearch\Request\CreateEngine;
use Elastic\EnterpriseSearch\AppSearch\Request\DeleteDocuments;
use Elastic\EnterpriseSearch\AppSearch\Request\GetDocuments;
use Elastic\EnterpriseSearch\AppSearch\Request\GetSchema;
use Elastic\EnterpriseSearch\AppSearch\Request\IndexDocuments;
use Elastic\EnterpriseSearch\AppSearch\Request\ListDocuments;
use Elastic\EnterpriseSearch\AppSearch\Request\ListEngines;
use Elastic\EnterpriseSearch\AppSearch\Request\PutSchema;
use Elastic\EnterpriseSearch\AppSearch\Schema\Engine;
use Elastic\EnterpriseSearch\AppSearch\Schema\SchemaUpdateRequest;
use Elastic\EnterpriseSearch\Client;
use Exception;
use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Exception\IndexingServiceException;
use SilverStripe\SearchService\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Schema\Field;
use SilverStripe\SearchService\Service\DocumentBuilder;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;

class EnterpriseSearchService implements IndexingInterface, BatchDocumentRemovalInterface
{

    use Configurable;
    use ConfigurationAware;
    use Injectable;

    private const DEFAULT_FIELD_TYPE = 'text';

    private Client $client;

    private DocumentBuilder $builder;

    private static int $max_document_size = 102400;

    public function __construct(Client $client, IndexConfiguration $configuration, DocumentBuilder $exporter)
    {
        $this->setClient($client);
        $this->setConfiguration($configuration);
        $this->setBuilder($exporter);
    }

    public static function environmentizeIndex(string $indexName): string
    {
        $variant = IndexConfiguration::singleton()->getIndexVariant();

        if ($variant) {
            return sprintf('%s-%s', $variant, $indexName);
        }

        return $indexName;
    }

    public function getExternalURL(): ?string
    {
        return Environment::getEnv('ENTERPRISE_SEARCH_ENDPOINT') ?: null;
    }

    public function getExternalURLDescription(): ?string
    {
        return 'Elastic Enterprise Search Dashboard';
    }

    public function getDocumentationURL(): ?string
    {
        return 'https://www.elastic.co/guide/en/app-search/current/guides.html';
    }

    /**
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     */
    public function addDocument(DocumentInterface $document): ?string
    {
        $processedIds = $this->addDocuments([$document]);

        return array_shift($processedIds);
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     */
    public function addDocuments(array $documents): array
    {
        $documentMap = $this->getContentMapForDocuments($documents);
        $processedIds = [];

        foreach ($documentMap as $indexName => $docsToAdd) {
            $response = $this->getClient()->appSearch()
                ->indexDocuments(new IndexDocuments(static::environmentizeIndex($indexName), $docsToAdd))
                ->asArray();

            $this->handleError($response);

            // Grab all the ID values, and also cast them to string
            $processedIds += array_map('strval', array_column($response, 'id'));
        }

        // One document could have existed in multiple indexes, we only care to track it once
        return array_unique($processedIds);
    }

    /**
     * @param DocumentInterface $document
     * @throws Exception
     */
    public function removeDocument(DocumentInterface $document): ?string
    {
        $processedIds = $this->removeDocuments([$document]);

        return array_shift($processedIds);
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws Exception
     */
    public function removeDocuments(array $documents): array
    {
        $documentMap = [];
        $processedIds = [];

        foreach ($documents as $document) {
            if (!$document instanceof DocumentInterface) {
                throw new InvalidArgumentException(sprintf(
                    '%s not passed an instance of %s',
                    __FUNCTION__,
                    DocumentInterface::class
                ));
            }

            $indexes = $this->getConfiguration()->getIndexesForDocument($document);

            foreach (array_keys($indexes) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }

                $documentMap[$indexName][] = $document->getIdentifier();
            }
        }

        foreach ($documentMap as $indexName => $idsToRemove) {
            $response = $this->getClient()->appSearch()
                ->deleteDocuments(new DeleteDocuments(static::environmentizeIndex($indexName), $idsToRemove))
                ->asArray();

            $this->handleError($response);

            // Results here can be marked as deleted true or false. false would indicate that no document with that ID
            // exists in Elastic - which, is the same result, really
            // Grab all the ID values, and also cast them to string
            $processedIds += array_map('strval', array_column($response, 'id'));
        }

        // One document could have existed in multiple indexes, we only care to track it once
        return array_unique($processedIds);
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
        $indexName = static::environmentizeIndex($indexName);
        $cfg = $this->getConfiguration();
        $client = $this->getClient();
        $numDeleted = 0;

        $request = new ListDocuments($indexName);
        $request->setPageSize($cfg->getBatchSize());
        $request->setCurrentPage(1);

        $response = $client->appSearch()
            ->listDocuments($request)
            ->asArray();

        $this->handleError($response);

        $results = $response['results'] ?? [];

        // Loop forever until we no longer get any results
        while (count($results) > 0) {
            $idsToRemove = [];

            // Create the list of indexed documents to remove
            foreach ($response['results'] as $doc) {
                $idsToRemove[] = $doc['id'];
            }

            // Actually delete the documents
            $deletedDocs = $client->appSearch()
                ->deleteDocuments(new DeleteDocuments($indexName, $idsToRemove))
                ->asArray();

            // Keep an accurate running count of the number of documents deleted.
            foreach ($deletedDocs as $doc) {
                $deleted = $doc['deleted'] ?? false;

                if ($deleted) {
                    $numDeleted++;
                }
            }

            // Re-fetch $documents now that we've deleted this batch
            $response = $client->appSearch()
                ->listDocuments($request)
                ->asArray();

            $this->handleError($response);

            $results = $response['results'] ?? [];
        }

        return $numDeleted;
    }

    public function getMaxDocumentSize(): int
    {
        return $this->config()->get('max_document_size');
    }

    /**
     * @throws IndexingServiceException
     */
    public function getDocument(string $id): ?DocumentInterface
    {
        $result = $this->getDocuments([$id]);

        return $result[0] ?? null;
    }

    /**
     * @return DocumentInterface[]
     * @throws IndexingServiceException
     */
    public function getDocuments(array $ids): array
    {
        $docs = [];

        foreach (array_keys($this->getConfiguration()->getIndexes()) as $indexName) {
            $response = $this->getClient()->appSearch()
                ->getDocuments(new GetDocuments(static::environmentizeIndex($indexName), $ids))
                ->asArray();

            $this->handleError($response);

            $results = $response['results'] ?? null;

            if (!$results) {
                continue;
            }

            foreach ($results as $data) {
                $document = $this->getBuilder()->fromArray($data);

                if (!$document) {
                    continue;
                }

                // Stored by identifier as the key just in case one record exists in multiple indexes
                $docs[$document->getIdentifier()] = $document;
            }
        }

        return array_values($docs);
    }

    /**
     * @return DocumentInterface[]
     * @throws Exception
     */
    public function listDocuments(string $indexName, ?int $pageSize = null, int $currentPage = 0): array
    {
        $request = new ListDocuments(static::environmentizeIndex($indexName));
        $request->setCurrentPage($currentPage);

        if ($pageSize) {
            $request->setPageSize($pageSize);
        }

        $response = $this->getClient()->appSearch()
            ->listDocuments($request)
            ->asArray();

        $this->handleError($response);

        $results = $response['results'] ?? null;

        if (!$results) {
            return [];
        }

        $documents = [];

        foreach ($results as $data) {
            $document = $this->getBuilder()->fromArray($data);

            if (!$document) {
                continue;
            }

            $documents[] = $document;
        }

        return $documents;
    }

    /**
     * @throws IndexingServiceException
     */
    public function getDocumentTotal(string $indexName): int
    {
        $response = $this->getClient()->appSearch()
            ->listDocuments(new ListDocuments(static::environmentizeIndex($indexName)))
            ->asArray();

        $this->handleError($response);

        $total = $response['meta']['page']['total_results'] ?? null;

        if ($total === null) {
            throw new IndexingServiceException('Total results not provided in meta content');
        }

        return $total;
    }

    /**
     * Ensure all the engines exist
     *
     * @throws IndexingServiceException
     * @throws IndexConfigurationException
     */
    public function configure(): array
    {
        $schemas = [];

        foreach (array_keys($this->getConfiguration()->getIndexes()) as $indexName) {
            $this->validateIndex($indexName);

            $envIndex = static::environmentizeIndex($indexName);
            $this->findOrMakeIndex($envIndex);

            // Fetch the Schema, as it currently exists in Elastic
            $elasticSchema = $this->getClient()->appSearch()
                ->getSchema(new GetSchema($envIndex))
                ->asArray();

            $this->handleError($elasticSchema);

            // Fetch the Schema, as it is currently configured in our application
            $definedSchema = $this->getSchemaForFields($this->getConfiguration()->getFieldsForIndex($indexName));

            // Check to see if there are any important differences between our Schemas. If there is, we'll want to
            // update
            if (!$this->schemaRequiresUpdate($definedSchema, $elasticSchema)) {
                // No updates found, add this to our tracked Schemas
                $schemas[$indexName] = $elasticSchema;

                continue;
            }

            // Trigger an update to Elastic with our current configured Schema
            $newElasticSchema = $this->getClient()->appSearch()
                ->putSchema(new PutSchema($envIndex, $definedSchema))
                ->asArray();

            $this->handleError($newElasticSchema);

            // Add this updated Schema to our tracked Schemas
            $schemas[$indexName] = $newElasticSchema;
        }

        return $schemas;
    }

    /**
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

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getBuilder(): DocumentBuilder
    {
        return $this->builder;
    }

    private function setClient(Client $client): EnterpriseSearchService
    {
        $this->client = $client;

        return $this;
    }

    private function setBuilder(DocumentBuilder $builder): EnterpriseSearchService
    {
        $this->builder = $builder;

        return $this;
    }

    /**
     * @throws IndexingServiceException
     */
    private function findOrMakeIndex(string $index): void
    {
        $allEngines = $this->fetchEngines();

        if (in_array($index, $allEngines)) {
            return;
        }

        $response = $this->getClient()
            ->appSearch()
            ->createEngine(new CreateEngine(new Engine($index)))
            ->asArray();

        $this->handleError($response);
    }

    private function fetchEngines(): array
    {
        $response = $this->getClient()
            ->appSearch()
            ->listEngines(new ListEngines())
            ->asArray();

        $this->handleError($response);

        if (!array_key_exists('results', $response)) {
            throw new IndexingServiceException('Invalid response format for listEngines; missing "results"');
        }

        $results = $response['results'] ?? [];

        return array_column($results, 'name');
    }

    /**
     * @throws IndexingServiceException
     */
    private function handleError(?array $responseBody): void
    {
        if (!is_array($responseBody)) {
            return;
        }

        $errors = array_column($responseBody, 'errors');

        if (!$errors) {
            return;
        }

        $allErrors = [];

        foreach ($errors as $errorGroup) {
            $allErrors = array_merge($allErrors, $errorGroup);
        }

        if (!$allErrors) {
            return;
        }

        throw new IndexingServiceException(sprintf(
            'EnterpriseSearch API error: %s',
            print_r($allErrors, true)
        ));
    }

    /**
     * @param Field[] $fields
     */
    private function getSchemaForFields(array $fields): SchemaUpdateRequest
    {
        $definedSpecs = new SchemaUpdateRequest();

        foreach ($fields as $field) {
            $explicitFieldType = $field->getOption('type') ?? self::DEFAULT_FIELD_TYPE;
            $definedSpecs->{$field->getSearchFieldName()} = $explicitFieldType;
        }

        return $definedSpecs;
    }

    /**
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

        // Note: IndexConfiguration::getFieldsForIndex($index) does exist, and we could use that instead; However!
        // getFieldsForIndex() performs an array_merge() as it traverses through our classes, which means that
        // it (invisibly) removes duplicate fields
        // This is not ideal, as it means that we will never find out if two fields with the same name have been given
        // different types (which is a huge part of what this method should be about)
        // We want to be told when our configuration is invalid, we don't want it just *drop* one of our type
        // definitions

        // Loop through each Class that has a definition for this index
        foreach ($this->getConfiguration()->getClassesForIndex($index) as $class) {
            // Loop through each field that has been defined for that Class
            foreach ($this->getConfiguration()->getFieldsForClass($class) as $field) {
                // Check to see if a Type has been defined, or just default to what we have defined
                $type = $field->getOption('type') ?? self::DEFAULT_FIELD_TYPE;

                // We can't progress if a type that we don't support has been defined
                if (!in_array($type, $validTypes)) {
                    throw new IndexConfigurationException(sprintf(
                        'Invalid field type: %s',
                        $type
                    ));
                }

                // Check to see if this field name has been defined by any other Class, and if it has, let's grab what
                // "type" it was described as
                $alreadyDefined = $map[$field->getSearchFieldName()] ?? null;

                // This field name has been defined by another Class, and it was described as a different type. We
                // don't support multiple types for a field, so we need to throw an Exception
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

                // Store this field and its type for later comparison
                $map[$field->getSearchFieldName()] = $type;
            }
        }
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     */
    private function getContentMapForDocuments(array $documents): array
    {
        $documentMap = [];

        foreach ($documents as $document) {
            if (!$document instanceof DocumentInterface) {
                throw new InvalidArgumentException(sprintf(
                    '%s not passed an instance of %s',
                    __FUNCTION__,
                    DocumentInterface::class
                ));
            }

            if (!$document->shouldIndex()) {
                continue;
            }

            try {
                $fields = $this->getBuilder()->toArray($document);
            } catch (IndexConfigurationException $e) {
                Injector::inst()->get(LoggerInterface::class)->warning(
                    sprintf('Failed to convert document to array: %s', $e->getMessage())
                );

                continue;
            }

            $indexes = $this->getConfiguration()->getIndexesForDocument($document);

            if (!$indexes) {
                Injector::inst()->get(LoggerInterface::class)->warn(
                    sprintf('No valid indexes found for document %s, skipping...', $document->getIdentifier())
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

        return $documentMap;
    }

    private function schemaRequiresUpdate(SchemaUpdateRequest $definedSchema, array $elasticSchema): bool
    {
        // First we'll loop through the Elastic Schema to see if any current fields have changed in type. If one
        // or more has, then we know we need to update the Schema, and we can break; early
        foreach ($elasticSchema as $fieldName => $type) {
            $definedType = $definedSchema->{$fieldName} ?? null;

            // This field (potentially) no longer exists in our configured Schema
            if (!$definedType) {
                continue;
            }

            // The type has changed. We know we need to update, so we can return now
            if ($definedType !== $type) {
                return true;
            }
        }

        // Next we'll loop through our configuration Schema and see if any new fields exists that we haven't yet
        // defined in the Elastic Schema
        // The easiest thing to do here is just cast the Schema as an array, which turns all Class properties into array
        // keys
        foreach (array_keys((array) $definedSchema) as $fieldName) {
            // Check to see if this field exists in the Elastic Schema
            $existingType = $elasticSchema[$fieldName] ?? null;

            // If it doesn't, then we know we need to update, and we can return now
            if (!$existingType) {
                return true;
            }
        }

        // We got all the way to the end, and didn't find anything that needed to be updated
        return false;
    }

}
