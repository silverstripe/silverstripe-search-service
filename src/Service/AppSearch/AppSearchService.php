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
                $this->getClient()->deleteDocuments(static::environmentizeIndex($indexName), $documentIds);
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
            $result = $this->getClient()->getEngine($index);
            if (empty($result)) {
                $this->getClient()->createEngine($index);
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



}
