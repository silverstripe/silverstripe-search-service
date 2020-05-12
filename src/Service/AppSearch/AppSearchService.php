<?php

namespace SilverStripe\SearchService\Services\AppSearch;

use Elastic\AppSearch\Client\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\SearchService\Exception\IndexConfigurationException;
use SilverStripe\SearchService\Interfaces\BatchDocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use InvalidArgumentException;
use Exception;
use SilverStripe\SearchService\Service\IndexConfiguration;

class AppSearchService implements SearchServiceInterface
{
    use Configurable;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     * @config
     */
    private static $indexes = [];

    /**
     * AppSearchService constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param DocumentInterface $item
     * @return SearchServiceInterface
     * @throws Exception
     */
    public function addDocument(DocumentInterface $item): SearchServiceInterface
    {
        $this->addDocuments([$item]);

        return $this;
    }

    /**
     * @param DocumentInterface[] $items
     * @return BatchDocumentInterface
     * @throws Exception
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

            $fields = $item->toArray();
            $fields['id'] = $item->getIdentifier();

            foreach ($this->getIndexesForObject($item) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }
                $documentMap[$indexName][] = $fields;
            }
        }

        try {
            foreach ($documentMap as $indexName => $docsToAdd) {
                $result = $this->getClient()->indexDocuments(
                    static::environmentizeIndex($indexName),
                    $docsToAdd
                );
                $this->handleError($result);
            }
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);

            if (Director::isDev()) {
                throw $e;
            }
        }

        return $this;
    }

    /**
     * @param DocumentInterface $doc
     * @return SearchServiceInterface
     * @throws Exception
     */
    public function removeDocument(DocumentInterface $doc): SearchServiceInterface
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
                    '%s not passed a %s',
                    __FUNCTION__,
                    DocumentInterface::class
                ));
            }

            foreach ($this->getIndexesForObject($item) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }
                $documentMap[$indexName][] = $item->getIdentifier();
            }
        }

        try {
            foreach ($documentMap as $indexName => $documentIds) {
                $result = $this->getClient()->deleteDocuments(
                    static::environmentizeIndex($indexName),
                    $documentIds
                );
                $this->handleError($result);
            }
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);

            if (Director::isDev()) {
                throw $e;
            }
        }

        return $this;
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getDocument(string $id): ?array
    {
        $result = $this->getDocuments([$id]);

        return $result[$id] ?? null;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getDocuments(array $ids): array
    {
        $docs = [];
        foreach ($ids as $docID) {
            foreach (array_keys($this->config()->get('indexes')) as $indexName) {
                try {
                    $response = $this->getClient()->getDocuments(
                        static::environmentizeIndex($indexName),
                        [$docID]
                    );
                    $this->handleError($response);
                    if ($response) {
                        $docs[$docID] = $response;
                        break;
                    }
                } catch (Exception $e) {
                }
            }
        }

        return $docs;
    }

    /**
     * Ensure all the engines exist
     */
    public function configure(): void
    {
        $indexes = array_map(
            [static::class, 'environmentizeIndex'],
            array_keys($this->config()->get('indexes'))
        );

        foreach ($indexes as $index) {
            $engines = $this->getClient()->listEngines();
            $this->handleError($engines);
            $results = $engines['results'] ?? [];
            $allEngines = array_column($results, 'name');
            if (!in_array($index, $allEngines)) {
                $result = $this->getClient()->createEngine($index);
                $this->handleError($result);
            }
        }
    }

    /**
     * @return array
     */
    public function getSearchableClasses(): array
    {
        return array_reduce($this->config()->get('indexes'), function ($all, $curr) {
            return array_merge($all, $curr['includeClasses'] ?? []);
        }, []);
    }

    /**
     * @param string $field
     * @throws IndexConfigurationException
     */
    public function validateField(string $field): void
    {
        if (preg_match('/[^A-Za-z0-9_]/', $field)) {
            throw new IndexConfigurationException(sprintf(
                'Invalid field name: %s. Must contain only alphanumeric characters and underscores.',
                $field
            ));
        }
    }

    /**
     * @param DataObject $item
     * @return array
     */
    private function getIndexesForObject(DataObject $item): array
    {
        $matches = [];
        foreach ($this->config()->get('indexes') as $indexName => $data) {
            $classes = $data['includeClasses'] ?? [];
            foreach ($classes as $candidate) {
                if ($item instanceof $candidate) {
                    $matches[] = $indexName;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @param array|null $result
     * @throws Exception
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
        throw new Exception(sprintf(
            'AppSearch API error: %s',
            json_encode($allErrors)
        ));
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
     * @param string $indexName
     * @return string
     */
    public static function environmentizeIndex(string $indexName)
    {
        $variant = IndexConfiguration::singleton()->getIndexVariant();
        if ($variant) {
            return sprintf("%s-%s", $variant, $indexName);
        }

        return $indexName;
    }

}
