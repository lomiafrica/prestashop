<?php
/**
 * HTTP client for Lomi API (checkout sessions).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class LomiApiClient
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $secretKey;

    /**
     * @param bool $testMode
     * @param string $secretKey
     */
    public function __construct($testMode, $secretKey)
    {
        $this->baseUrl = $testMode
            ? 'https://sandbox.api.lomi.africa'
            : 'https://api.lomi.africa';
        $this->secretKey = trim((string) $secretKey);
    }

    /**
     * @param array $body
     * @return array{ok:bool,data?:object,error?:string,http_code?:int}
     */
    public function createCheckoutSession(array $body)
    {
        return $this->request('POST', '/checkout-sessions', $body);
    }

    /**
     * @param string $sessionId
     * @return array{ok:bool,data?:object,error?:string,http_code?:int}
     */
    public function getCheckoutSession($sessionId)
    {
        $path = '/checkout-sessions/' . rawurlencode($sessionId);

        return $this->request('GET', $path, null);
    }

    /**
     * @return array{ok:bool,data?:object,error?:string,http_code?:int}
     */
    public function getMe()
    {
        return $this->request('GET', '/me', null);
    }

    /**
     * @param int $httpCode
     * @param string $detail
     * @param object|null $json
     * @param bool $testMode
     * @return string
     */
    public static function formatMerchantFacingError($httpCode, $detail, $json = null, $testMode = true)
    {
        $reference = '';
        if (is_object($json) && !empty($json->request_id)) {
            $reference = ' (Reference: ' . (string) $json->request_id . ')';
        } elseif (is_object($json) && !empty($json->correlation_id)) {
            $reference = ' (Reference: ' . (string) $json->correlation_id . ')';
        }

        $modeLabel = $testMode ? 'test' : 'live';

        switch ((int) $httpCode) {
            case 401:
                return 'Invalid secret API key for ' . $modeLabel . ' mode. Copy the key from Dashboard → Developers → API keys and ensure test mode matches your keys.' . $reference;
            case 403:
                return 'This API key cannot perform this action. Check key permissions in the lomi. dashboard.' . $reference;
            case 404:
                return 'lomi. could not find this resource. Confirm test/live mode matches the keys in your dashboard.' . $reference;
            case 422:
                return 'Payment could not be created: ' . $detail . '.' . $reference;
            default:
                return 'lomi. returned an error (' . (int) $httpCode . '): ' . $detail . '.' . $reference . ' See docs.lomi.africa/build/ecommerce-extensions/prestashop.';
        }
    }

    /**
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array{ok:bool,data?:object,error?:string,http_code?:int}
     */
    private function request($method, $path, $body)
    {
        if ($this->secretKey === '') {
            return array('ok' => false, 'error' => 'Missing API secret key', 'http_code' => 0);
        }

        $url = $this->baseUrl . $path;
        $headers = array(
            'X-API-Key: ' . $this->secretKey,
            'Accept: application/json',
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method === 'POST' ? 'POST' : $method);
            if ($method === 'POST' && $body !== null) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return array('ok' => false, 'error' => 'Network error connecting to Lomi API', 'http_code' => $httpCode);
        }

        $json = json_decode($raw);

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = 'Lomi API request failed';
            if (is_object($json) && !empty($json->message)) {
                $msg = (string) $json->message;
            }

            return array(
                'ok' => false,
                'error' => self::formatMerchantFacingError($httpCode, $msg, is_object($json) ? $json : null, strpos($this->baseUrl, 'sandbox') !== false),
                'http_code' => $httpCode,
            );
        }

        $data = self::normalizeSessionPayload($json);

        return array('ok' => true, 'data' => $data, 'http_code' => $httpCode);
    }

    /**
     * @param mixed $json
     * @return object|null
     */
    public static function normalizeSessionPayload($json)
    {
        $payload = $json;
        if (is_object($json) && isset($json->data)) {
            $payload = $json->data;
        }
        if (is_array($payload) && !empty($payload[0]) && is_object($payload[0])) {
            $payload = $payload[0];
        }

        return is_object($payload) ? $payload : null;
    }
}
