<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Schema\Field;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class IndexConfiguration
{
    use Configurable;
    use Injectable;

    /**
     * @var bool
     * @config
     */
    private static $enabled = true;

    /**
     * @var bool
     * @config
     */
    private static $use_queuedjobs = false;

    /**
     * @var int
     * @config
     */
    private static $batch_size = 100;

    /**
     * @var string
     * @config
     */
    private static $sync_interval = '2 hours';

    /**
     * @var bool
     * @config
     */
    private static $crawl_page_content = true;

    /**
     * @var array
     * @config
     */
    private static $indexes = [];

    /**
     * @var string|null
     */
    private $indexVariant;

    /**
     * IndexConfiguration constructor.
     * @param string|null $indexVariant
     */
    public function __construct(string $indexVariant = null)
    {
        $this->setIndexVariant($indexVariant);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config()->get('enabled');
    }

    /**
     * @return bool
     */
    public function isUsingQueuedJobs(): bool
    {
        return $this->config()->get('use_queuedjobs') && class_exists(QueuedJobService::class);
    }

    /**
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->config()->get('batch_size');
    }

    /**
     * @return string
     */
    public function getSyncInterval(): string
    {
        return $this->config()->get('sync_interval');
    }

    /**
     * @return string
     */
    public function getIndexVariant(): string
    {
        return $this->indexVariant;
    }

    /**
     * @param string|null $variant
     * @return $this
     */
    public function setIndexVariant(?string $variant): self
    {
        $this->indexVariant = $variant;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldCrawlPageContent(): bool
    {
        return $this->config()->get('crawl_page_content');
    }

    /**
     * @return array
     * @config
     */
    public function getIndexes(): array
    {
        return $this->config()->get('indexes');
    }

    /**
     * @param string $class
     * @return array
     */
    public function getIndexesForClassName(string $class): array
    {
        $matches = [];
        foreach ($this->getIndexes() as $indexName => $data) {
            $classes = $data['includeClasses'] ?? [];
            foreach ($classes as $candidate => $spec) {
                if ($spec === false) {
                    continue;
                }
                if ($class === $candidate || is_subclass_of($class, $candidate)) {
                    $matches[$indexName] = $data;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @param string $index
     * @return array
     */
    public function getClassesForIndex(string $index): array
    {
        $index = $this->getIndexes()[$index] ?? null;
        if (!$index) {
            return [];
        }

        $classes = $index['includeClasses'] ?? [];

        return array_keys($classes);
    }

    /**
     * @return array
     */
    public function getSearchableClasses(): array
    {
        $classes = [];
        foreach ($this->getIndexes() as $config) {
            $includedClasses = $config['includeClasses'] ?? [];
            foreach ($includedClasses as $class => $spec) {
                if ($spec === false) {
                    continue;
                }
                $classes[$class] = true;
            }
        }

        return array_keys($classes);
    }

    /**
     * @param string $class
     * @return Field[]|null
     */
    public function getFieldsForClass(string $class): ?array
    {
        foreach ($this->getIndexes() as $config) {
            $includedClasses = $config['includeClasses'] ?? [];
            if (!isset($includedClasses[$class])) {
                continue;
            }
            $spec = $includedClasses[$class];
            if ($spec === false) {
                continue;
            }
            if (is_array($spec) && !empty($spec)) {
                $fields = $spec['fields'] ?? [];
                $fieldObjs = [];
                foreach ($fields as $searchName => $data) {
                    if ($data === false) {
                        continue;
                    }
                    $config = (array) $data;
                    $fieldObjs[] = new Field(
                        $searchName,
                        $config['property'] ?? null,
                        $config['options'] ?? []
                    );
                }

                return $fieldObjs;
            }
        }

        return null;
    }

    public function getFieldsForIndex(string $index): array
    {
        $fields = [];
        $classes = $this->getClassesForIndex($index);
        foreach ($classes as $class) {
            $fields = array_merge($fields, $this->getFieldsForClass($class));
        }

        return $fields;
    }


}
