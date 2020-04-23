<?php

namespace SilverStripe\SearchService\Services\AppSearch;

use Elastic\AppSearch\Client\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\SearchServiceInterface;
use SilverStripe\SearchService\Service\DocumentBuilder;
use InvalidArgumentException;
use Exception;

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
     * @param DataObject $item
     * @return SearchServiceInterface
     * @throws Exception
     */
    public function addDocument(DataObject $item): SearchServiceInterface
    {
        return $this->addDocuments([$item]);
    }

    /**
     * @param array $items
     * @return SearchServiceInterface
     * @throws Exception
     */
    public function addDocuments(array $items): SearchServiceInterface
    {
        $documentMap = [];
        /* @var DataObject|SearchServiceExtension $item */
        foreach ($items as $item) {
            if (!$item instanceof DataObject || !$item->hasExtension(SearchServiceExtension::class)) {
                var_dump($item);
                throw new InvalidArgumentException(sprintf(
                    '%s not passed a DataObject or an item does not have the %s extension',
                    __FUNCTION__,
                    SearchServiceExtension::class
                ));
            }

            $fields = DocumentBuilder::create($item)->exportAttributes();
            $fields->push('id', $item->generateSearchUUID());

            foreach ($this->getIndexesForObject($item) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }
                $documentMap[$indexName][] = $fields->toArray();
            }
        }

        try {
            foreach ($documentMap as $indexName => $docsToAdd) {
                $result = $this->getClient()->indexDocuments(static::environmentizeIndex($indexName), $docsToAdd);
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
     * @param DataObject $item
     * @return SearchServiceInterface
     * @throws Exception
     */
    public function removeDocument(DataObject $item): SearchServiceInterface
    {
        return $this->removeDocuments([$item]);
    }

    /**
     * @param array $items
     * @return SearchServiceInterface
     * @throws Exception
     */
    public function removeDocuments(array $items): SearchServiceInterface
    {
        $documentMap = [];
        /* @var DataObject|SearchServiceExtension $item */
        foreach ($items as $item) {
            if (!$item instanceof DataObject || !$item->hasExtension(SearchServiceExtension::class)) {
                throw new InvalidArgumentException(sprintf(
                    '%s not passed a DataObject or an item does not have the %s extension',
                    __FUNCTION__,
                    SearchServiceExtension::class
                ));
            }

            foreach ($this->getIndexesForObject($item) as $indexName) {
                if (!isset($documentMap[$indexName])) {
                    $documentMap[$indexName] = [];
                }
                $documentMap[$indexName][] = $item->generateSearchUUID();
            }
        }

        try {
            foreach ($documentMap as $indexName => $documentIds) {
                $result = $this->getClient()->deleteDocuments(static::environmentizeIndex($indexName), $documentIds);
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
                    $response = $this->getClient()->getDocuments(static::environmentizeIndex($indexName), [$docID]);
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
     *
     * @return string
     */
    public static function environmentizeIndex($indexName)
    {
        return sprintf("%s-%s", Director::get_environment_type(), $indexName);
    }

    /**
     * @param string $field
     * @return string
     */
    public static function formatField(string $field): string
    {
        $clean = preg_replace('/[^A-Za-z_\-0-9]/', '', $field);
        $clean = preg_replace('/([a-z])([A-Z])/', '\1-\2', $clean);
        $clean = str_replace('-', '_', $clean);
        $clean = strtolower($clean);

        return $clean;
    }

}
