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
        $host = $params['host'] ?? null;
        $token = $params['token'] ?? null;

        if (!$host || !$token) {
            throw new Exception(sprintf(
                'The %s implementation requires environment variables: ' .
                'ENTERPRISE_SEARCH_ENDPOINT and ENTERPRISE_SEARCH_API_KEY',
                Client::class
            ));
        }

        return new Client([
            'host' => $host,
            'app-search' => [
                'token' => $token,
            ],
        ]);
    }
}
