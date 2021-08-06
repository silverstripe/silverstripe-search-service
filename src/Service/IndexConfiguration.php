<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Schema\Field;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class IndexConfiguration
{
    use Configurable;
    use Injectable;
    use Extensible;

    /**
     * @var bool
     * @config
     */
    private static $enabled = true;

    /**
     * @var int
     * @config
     */
    private static $batch_size = 100;

    /**
     * @var bool
     * @config
     */
    private static $crawl_page_content = true;

    /**
     * @var bool
     * @config
     */
    private static $include_page_html = false;

    /**
     * @var array
     * @config
     */
    private static $indexes = [];

    /**
     * @var bool
     * @config
     */
    private static $use_sync_jobs = false;

    /**
     * @var string
     */
    private static $id_field = 'id';

    /**
     * @var string
     * @config
     */
    private static $source_class_field = 'source_class';

    /**
     * @var string|null
     */
    private $indexVariant;

    /**
     * @var string[]
     */
    private $onlyIndexes = [];

    /**
     * @var bool
     * @config
     */
    private static $auto_dependency_tracking = true;

    /**
     * @var array
     */
    private $indexesForClassName = [];

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
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->config()->get('batch_size');
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
     * @return bool
     */
    public function shouldIncludePageHTML(): bool
    {
        return $this->config()->get('include_page_html');
    }

    /**
     * @param array $indexes
     * @return $this
     */
    public function setOnlyIndexes(array $indexes): IndexConfiguration
    {
        $this->onlyIndexes = $indexes;
        return $this;
    }

    /**
     * @return array
     * @config
     */
    public function getIndexes(): array
    {
        $indexes = $this->config()->get('indexes');
        if ($this->onlyIndexes && !empty($this->onlyIndexes)) {
            foreach ($indexes as $index => $configuration) {
                if (!in_array($index, $this->onlyIndexes)) {
                    unset($indexes[$index]);
                }
            }
        }

        return $indexes;
    }

    /**
     * @return bool
     */
    public function shouldUseSyncJobs(): bool
    {
        return $this->config()->get('use_sync_jobs');
    }

    /**
     * @return string
     */
    public function getIDField(): string
    {
        return $this->config()->get('id_field');
    }

    /**
     * @return string
     */
    public function getSourceClassField(): string
    {
        return $this->config()->get('source_class_field');
    }

    /**
     * @return bool
     */
    public function shouldTrackDependencies(): bool
    {
        return $this->config()->get('auto_dependency_tracking');
    }

    /**
     * @param string $class
     * @return array
     */
    public function getIndexesForClassName(string $class): array
    {

        if (!isset($this->indexesForClassName[$class])) {
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

            $this->indexesForClassName[$class] = $matches;
        }

        return $this->indexesForClassName[$class];
    }

    /**
     * @param DocumentInterface $doc
     * @return array
     */
    public function getIndexesForDocument(DocumentInterface $doc): array
    {
        $indexes = $this->getIndexesForClassName($doc->getSourceClass());

        $this->extend('updateIndexesForDocument', $doc, $indexes);

        return $indexes;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function isClassIndexed(string $class): bool
    {
        return !empty($this->getFieldsForClass($class));
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
        $result = [];

        foreach ($classes as $className => $spec) {
            if ($spec === false) {
                continue;
            }
            $result[] = $className;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getSearchableClasses(): array
    {
        $classes = [];
        foreach ($this->getIndexes() as $indexName => $config) {
            $classes = array_merge($classes, $this->getClassesForIndex($indexName));
        }

        return array_unique($classes);
    }

    /**
     * @return array
     */
    public function getSearchableBaseClasses(): array
    {
        $classes = $this->getSearchableClasses();
        $baseClasses = $classes;
        foreach ($classes as $class) {
            $baseClasses = array_filter($baseClasses, function ($possibleParent) use ($class) {
                return !is_subclass_of($possibleParent, $class);
            });
        }

        return $baseClasses;
    }

    /**
     * @param string $class
     * @return Field[]
     */
    public function getFieldsForClass(string $class): ?array
    {
        $candidate = $class;
        $fieldObjs = [];
        while ($candidate) {
            foreach ($this->getIndexes() as $config) {
                $includedClasses = $config['includeClasses'] ?? [];
                $spec = $includedClasses[$candidate] ?? null;
                if (is_array($spec) && !empty($spec)) {
                    $fields = $spec['fields'] ?? [];
                    foreach ($fields as $searchName => $data) {
                        if ($data === false) {
                            continue;
                        }
                        $config = (array)$data;
                        $fieldObjs[$searchName] = new Field(
                            $searchName,
                            $config['property'] ?? null,
                            $config['options'] ?? []
                        );
                    }
                }
            }
            $candidate = get_parent_class($candidate);
        }
        return $fieldObjs;
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
