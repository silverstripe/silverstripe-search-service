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
    public function create($service, array $params = []) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $host = $params['host'] ?? null;
        $token = $params['token'] ?? null;
        $httpClient = $params['http_client'] ?? null;

        if (!$host || !$token) {
            throw new Exception(sprintf(
                'The %s implementation requires environment variables: ' .
                'ENTERPRISE_SEARCH_ENDPOINT and ENTERPRISE_SEARCH_API_KEY',
                Client::class
            ));
        }

        $config = [
            'host' => $host,
            'app-search' => [
                'token' => $token,
            ],
        ];

        // If a desired HTTP Client has been defined and instantiated in config (@see enterprisesearch.yml) then we'll
        // set it here. If it hasn't been defined, then it will be left up to PSR-18 "discovery"
        if ($httpClient) {
            $config['client'] = $httpClient;
        }

        return new Client($config);
    }

}
