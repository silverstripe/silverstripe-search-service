<?php


namespace SilverStripe\SearchService\Service\EnterpriseSearch;

use Elastic\EnterpriseSearch\Client;
use Exception;
use SilverStripe\Core\Injector\Factory;

class ClientFactory implements Factory
{
    /**
     * @throws Exception
     */
    public function create($service, array $params = array())
    {
        $endPoint = $params['endpoint'] ?? null;
        $apiKey = $params['apiKey'] ?? null;

        if (!$endPoint || !$apiKey) {
            throw new Exception(sprintf(
                'The %s implementation requires environment variables: ' .
                'ENTERPRISE_SEARCH_ENDPOINT and ENTERPRISE_SEARCH_API_KEY',
                Client::class
            ));
        }

        return new Client([
            'host' => $endPoint,
            'app-search' => [
                'api-key' => $apiKey,
            ],
        ]);
    }
}
