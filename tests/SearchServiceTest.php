<?php

namespace SilverStripe\SearchService\Tests;

use Elastic\EnterpriseSearch\Client;
use PHPUnit\Framework\ExpectationFailedException;
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
    /**
     * @return IndexConfigurationFake
     */
    protected function mockConfig()
    {
        Injector::inst()->registerService($config = new IndexConfigurationFake(), IndexConfiguration::class);
        SearchServiceExtension::singleton()->setConfiguration($config);

        return $config;
    }

    /**
     * @return ServiceFake
     */
    protected function mockService()
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

    protected function mockClient(): Client
    {
        $client = $this->getMockBuilder(Client::class)
            ->onlyMethods([
                'indexDocuments',
                'deleteDocuments',
                'getDocuments',
                'listDocuments',
                'getSchema',
                'updateSchema',
                'listEngines',
                'createEngine',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        Injector::inst()->registerService($client, Client::class);

        return $client;
    }


    /**
     * @param array $arr
     * @param callable $func
     * @return bool
     */
    protected function assertArrayContainsCallback(array $arr, callable $func)
    {
        foreach ($arr as $item) {
            if ($func($item)) {
                return true;
            }
        }

        throw new ExpectationFailedException(sprintf(
            'Failed to assert that any item in the array satisfies the callback: %s',
            print_r($arr, true)
        ));
    }
}
