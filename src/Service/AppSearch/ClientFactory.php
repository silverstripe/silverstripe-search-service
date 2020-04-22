<?php


namespace SilverStripe\SearchService\Services\AppSearch;


use Elastic\AppSearch\Client\ClientBuilder;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Factory;
use Exception;

class ClientFactory implements Factory
{
    public function create($service, array $params = array())
    {
        $endPoint = Environment::getEnv('APP_SEARCH_ENDPOINT');
        $apiKey = Environment::getEnv('APP_SEARCH_API_KEY');

        if (!$endPoint || !$apiKey) {
            throw new Exception(sprintf(
                'The %s implementation requires environment variables: APP_SEARCH_ENDPOINT and APP_SEARCH_API_KEY'
            ));
        }

        $clientBuilder = ClientBuilder::create($endPoint, $apiKey);
        $client = $clientBuilder->build();

        return $client;
    }
}
