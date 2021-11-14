<?php


namespace SilverStripe\SearchService\Tests\Fake;

use Elastic\EnterpriseSearch\Client;
use SilverStripe\SearchService\Exception\IndexingServiceException;

class FakeAppsearchClient extends Client
{
    public function indexDocuments($engineName, $docs)
    {
        foreach ($docs as $doc) {
            $this->documents[$doc['id']] = $doc;
        }
    }

    public function deleteDocuments($engineName, $ids)
    {
        foreach ($ids as $id) {
            unset($this->documents[$id]);
        }
    }

    //public function
}
