<?php


namespace SilverStripe\SearchService\Service;


use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
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
    private static $batch_size = 20;

    /**
     * @var string
     * @config
     */
    private static $sync_interval = '2 hours';

    /**
     * @var string
     * @config
     */
    private static $index_variant = '`SS_ENVIRONMENT_TYPE`';

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
        return $this->config()->get('index_variant');
    }
}
