<?php

namespace Voronkovich\SberbankAcquiring;

use Voronkovich\SberbankAcquiring\Exception\ActionException;
use Voronkovich\SberbankAcquiring\Exception\NetworkException;
use Voronkovich\SberbankAcquiring\HttpClient\CurlClient;
use Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface;

/**
 * Client for working with Sberbanks's aquiring REST API.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 * @see http://www.sberbank.ru/ru/s_m_business/bankingservice/internet_acquiring
 */
class Client
{
    private $userName = '';
    private $password = '';

    private $apiUri = 'https://3dsec.sberbank.ru/payment/rest/';
    private $httpMethod = 'POST';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * Constructor.
     *
     * @param array $settings Client's settings
     */
    public function __construct(array $settings)
    {
        if (isset($settings['userName'])) {
            $this->userName = $settings['userName'];
        } else {
            throw new \LogicException('UserName is required.');
        }

        if (isset($settings['password'])) {
            $this->password = $settings['password'];
        } else {
            throw new \LogicException('Password is required.');
        }

        if (isset($settings['apiUri'])) {
            $this->apiUri = $settings['apiUri'];
        }

        if (isset($settings['httpMethod'])) {
            if ('GET' !== $settings['httpMethod'] && 'POST' !== $settings['httpMethod']) {
                throw new \UnexpectedValueException('An HTTP method must be "GET" or "POST".');
            }

            $this->httpMethod = $settings['httpMethod'];
        }

        if (isset($settings['httpClient'])) {
            if (!$settings instanceof HttpClientInterface) {
                throw new \UnexpectedValueException('An HTTP client must implement HttpClientInterface.');
            }

            $this->httpClient = $settings['httpClient'];
        }
    }

    /**
     * Execute an action.
     *
     * @param string $action An action's name e.g. 'register.do'
     * @param array  $data   An actions's data
     *
     * @throws ActionException
     * @throws NetworkException
     *
     * @return array A server's response
     */
    public function execute($action, array $data = array())
    {
        $uri = $this->apiUri . $action;

        $data['userName'] = $this->userName;
        $data['password'] = $this->password;

        $headers = array(
            'Content-type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
            'charset="utf-8"',
        );

        $httpClient = $this->getHttpClient();

        $response = $httpClient->request($uri, $this->httpMethod, $headers, $data);
        $response = json_decode($response, true);

        $this->handleResponseError($response);

        return $response;
    }

    /**
     * Get an HTTP client.
     *
     * @return HttpClientInterface
     */
    private function getHttpClient()
    {
        if (null === $this->httpClient) {
            $this->httpClient = new CurlClient(array(
                \CURLOPT_VERBOSE => false,
                \CURLOPT_SSL_VERIFYHOST => false,
                \CURLOPT_SSL_VERIFYPEER => false,
            ));
        }

        return $this->httpClient;
    }

    /**
     * Handle a response error.
     *
     * @param array $response A server's response
     *
     * @throws ActionException If an error was occuried
     */
    private function handleResponseError(array $response)
    {
        if (!isset($response['errorCode'])) {
            throw new ActionException('Malformed response: "errorCode" field not found.');
        }

        $errorCode = $response['errorCode'];

        if ('0' === $errorCode) {
            return;
        }

        $errorMessage = isset($response['errorMessage']) ? $response['errorMessage'] : 'Unknown error.';

        throw new ActionException($errorMessage, $errorCode);
    }
}