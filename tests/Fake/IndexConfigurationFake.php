<?php


namespace SilverStripe\SearchService\Tests\Fake;

use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Service\IndexConfiguration;

class IndexConfigurationFake extends IndexConfiguration
{
    public $override = [];

    public function set($setting, $value)
    {
        $this->override[$setting] = $value;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->override['enabled'] ?? parent::isEnabled();
    }

    public function getBatchSize(): int
    {
        return $this->override['batch_size'] ?? parent::getBatchSize();
    }

    public function getSyncInterval(): string
    {
        return $this->override['sync_interval'] ?? parent::getSyncInterval();
    }

    public function shouldCrawlPageContent(): bool
    {
        return $this->override['crawl_page_content'] ?? parent::shouldCrawlPageContent();
    }

    public function shouldIncludePageHTML(): bool
    {
        return $this->override['include_page_html'] ?? parent::shouldIncludePageHTML();
    }

    public function getIndexes(): array
    {
        return $this->override['indexes'] ?? parent::getIndexes();
    }

    public function shouldUseSyncJobs(): bool
    {
        return $this->override['use_sync_jobs'] ?? parent::shouldUseSyncJobs();
    }

    public function getIDField(): string
    {
        return $this->override['id_field'] ?? parent::getIDField();
    }

    public function getSourceClassField(): string
    {
        return $this->override['source_class_field'] ?? parent::getSourceClassField();
    }

    public function shouldTrackDependencies(): bool
    {
        return $this->override['auto_dependency_tracking'] ?? parent::shouldTrackDependencies();
    }

    public function getIndexesForClassName(string $class): array
    {
        return $this->override[__FUNCTION__][$class] ?? parent::getIndexesForClassName($class);
    }

    public function getIndexesForDocument(DocumentInterface $doc): array
    {
        return $this->override[__FUNCTION__][$doc->getIdentifier()] ?? parent::getIndexesForDocument($doc);
    }

    public function isClassIndexed(string $class): bool
    {
        return $this->override[__FUNCTION__][$class] ?? parent::isClassIndexed($class);
    }

    public function getClassesForIndex(string $index): array
    {
        return $this->override[__FUNCTION__][$index] ?? parent::getClassesForIndex($index);
    }

    public function getSearchableClasses(): array
    {
        return $this->override[__FUNCTION__] ?? parent::getSearchableClasses();
    }

    public function getSearchableBaseClasses(): array
    {
        return $this->override[__FUNCTION__] ?? parent::getSearchableBaseClasses();
    }

    public function getFieldsForClass(string $class): ?array
    {
        return $this->override[__FUNCTION__][$class] ?? parent::getFieldsForClass($class);
    }

    public function getFieldsForIndex(string $index): array
    {
        return $this->override[__FUNCTION__][$index] ?? parent::getFieldsForIndex($index);
    }

    public function getIndexVariant(): string
    {
        return $this->override[__FUNCTION__] ?? parent::getIndexVariant();
    }
}
