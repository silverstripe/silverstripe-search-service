<?php

namespace SilverStripe\SearchService\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SearchService\DataObject\DataObjectDocument;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Service\IndexConfiguration;
use SilverStripe\SearchService\Tests\Fake\DataObjectFake;
use SilverStripe\SearchService\Tests\Fake\IndexConfigurationFake;
use SilverStripe\SearchService\Tests\Fake\ServiceFake;

abstract class SearchServiceTest extends SapphireTest
{

    protected function mockConfig(): IndexConfigurationFake
    {
        Injector::inst()->registerService($config = new IndexConfigurationFake(), IndexConfiguration::class);
        SearchServiceExtension::singleton()->setConfiguration($config);

        return $config;
    }

    protected function mockService(): ServiceFake
    {
        Injector::inst()->registerService($service = new ServiceFake(), IndexingInterface::class);
        SearchServiceExtension::singleton()->setIndexService($service);

        return $service;
    }

    protected function loadIndex(int $count = 10): ServiceFake
    {
        $service = $this->mockService();

        for ($i = 0; $i < $count; $i++) {
            $dataobject = DataObjectFake::create([
                'Title' => 'Dataobject ' . $i,
            ]);

            $dataobject->write();
            $doc = DataObjectDocument::create($dataobject);
            $service->addDocument($doc);
        }

        return $service;
    }

}
