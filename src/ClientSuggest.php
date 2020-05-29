<?php

namespace Fomvasss\Dadata;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use RuntimeException;

/**
 * Class ClientHint
 *
 * @package \Fomvasss\Dadata
 */
class ClientSuggest
{

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    /**
     * Организации
     */
    const TYPE_PARTY = 'party';

    /**
     * Организации (алиас)
     */
    const TYPE_ORG = 'party';

    /**
     * Адреса
     */
    const TYPE_ADDRESS = 'address';

    /**
     * Банки
     */
    const TYPE_BANK = 'bank';

    /**
     * ФИО
     */
    const TYPE_FIO = 'fio';

    /**
     * email
     */
    const TYPE_EMAIL = 'email';

    /**
     * кем выдан паспорт
     */
    const TYPE_FMS_UNIT = 'fms_unit';

    /**
     * налоговые инспекции
     */
    const TYPE_FNS_UNIT = 'fns_unit';

    /**
     * отделения Почты России
     */
    const TYPE_POSTAL_OFFICE = 'postal_office';

    /**
     * мировые суды
     */
    const TYPE_REGION_COURT = 'region_court';

    /**
     * страны
     */
    const TYPE_COUNTRY = 'country';

    /**
     * валюты
     */
    const TYPE_CURRENCY = 'currency';

    /**
     * виды деятельности (ОКВЭД 2)
     */
    const TYPE_OKVED_2 = 'okved2';

    /**
     * виды продукции (ОКПД 2)
     */
    const TYPE_OKPD_2 = 'okpd2';

    /**
     * API-ключ
     *
     * @var string
     */
    protected $token;

    /**
     * Настройки
     *
     * @var array
     */
    protected $config;

    /**
     * Версия API
     *
     * @var string
     */
    protected $version;

    /**
     * Базовый адрес
     *
     * @var string
     */
    protected $base_url = 'https://suggestions.dadata.ru/suggestions/api';

    /**
     * URI подсказок
     *
     * @var string
     */
    protected $url_suggestions = 'suggest';

    /**
     * URI поиска организации по ИНН, ОГРН, HID
     *
     * @var string
     */
    protected $url_findById = 'findById/party';

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $httpOptions = [];

    /**
     * ClientHint constructor.
     */
    public function __construct()
    {
        $this->config = config('dadata');
        $this->token = $this->config['token'];
        $this->version = $this->config['version'];

        $this->httpClient = new Client();
    }

    /**
     * Организация по ИНН или ОГРН
     *
     * @link https://dadata.ru/api/find-party/
     * @param string $id ИНН, ОГРН, Dadata HID
     * @param array $params Дополнительные параметры
     * @return mixed
     */
    public function partyById($id, array $params = [])
    {
        $params['query'] = $id;

        return $this->request("{$this->url_findById}", $params);
    }

    /**
     * Requests API.
     *
     * @param string $url
     * @param array $params
     * @param string $method
     * @return mixed
     */
    private function query($url, array $params = [], $method = self::METHOD_POST)
    {
        if (empty($params['count'])) {
            $params['count'] = 1;
        }

        $request = new Request($method, $url, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Token ' . $this->token
        ], json_encode($params));

        try {
            $response = $this->httpClient->send($request, $this->httpOptions);
        } catch (GuzzleException $guzzleException) {
            throw new RuntimeException('Http exception: ' . $guzzleException->getMessage());
        } catch (Exception $exception) {
            throw new RuntimeException('Exception: ' . $exception->getMessage());
        }

        switch ($response->getStatusCode()) {
            case 200:
                // успешный запрос
                $result = json_decode($response->getBody(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Error parsing response: ' . json_last_error_msg());
                }

                if (empty($result['suggestions'][0])) {
                    throw new RuntimeException('Empty result');
                }

                return (int)$params['count'] === 1 ? $result['suggestions'][0] : $result['suggestions'];

                break;
            case 400:
                // DaData Некорректный запрос
                throw new RuntimeException('Incorrect request');
                break;
            case 401:
                // В запросе отсутствует API-ключ
                throw new RuntimeException('Missing API key');
                break;
            case 403:
                // В запросе указан несуществующий API-ключ
                throw new RuntimeException('Incorrect API key');
                break;
            case 405:
                // Запрос сделан с методом, отличным от POST
                throw new RuntimeException('Request method must be POST');
                break;
            case 413:
                // Нарушены ограничения
                throw new RuntimeException('You push the limits of suggestions');
                break;
            case 500:
                // Произошла внутренняя ошибка сервиса во время обработки
                throw new RuntimeException('Server internal error');
                break;
            default:
                throw new RuntimeException('Unexpected error');
        }
    }

    /**
     * Подсказки
     *
     * @link https://dadata.ru/api/suggest/
     * @param string $type
     * @param string $query
     * @param array $params
     * @return bool|mixed|string
     */
    public function suggest($type, $query, array $params = [])
    {
        $params['query'] = $query;
        return $this->request("{$this->url_suggestions}/$type", $params);
    }

    public function geoSuggest(array $query)
    {
        return $this->request('geolocate/' . static::TYPE_ADDRESS, $query);
    }

    public function request($suggestUrl, $query)
    {
        return $this->query("{$this->base_url}/{$this->version}/rs/$suggestUrl", $query);
    }
}