<?php


namespace SilverStripe\SearchService\Services\AppSearch;

use Elastic\EnterpriseSearch\Client;
use SilverStripe\Core\Injector\Factory;
use Exception;

class ClientFactory implements Factory
{
    public function create($service, array $params = array())
    {
        $endPoint = $params['endpoint'] ?? null;
        $apiKey = $params['apiKey'] ?? null;

        if (!$endPoint || !$apiKey) {
            throw new Exception(sprintf(
                'The %s implementation requires environment variables: APP_SEARCH_ENDPOINT and APP_SEARCH_API_KEY',
                Client::class
            ));
        }

        $client = new Client([
            'host' => $endPoint,
            'app-search' => [
                'token' => $apiKey,
            ]
        ]);

        return $client;
    }
}
