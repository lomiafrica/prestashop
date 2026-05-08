<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * lomi. webhook endpoint (PAYMENT_SUCCEEDED + X-Lomi-Signature).
 */
class LomiWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;
    public $guestAllowed = true;

    public function init()
    {
        parent::init();
        $this->processWebhook();
    }

    protected function processWebhook()
    {
        if (!$this->module->active) {
            http_response_code(503);
            exit;
        }

        if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
            http_response_code(405);
            exit;
        }

        $raw = (string) file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_LOMI_SIGNATURE']) ? (string) $_SERVER['HTTP_X_LOMI_SIGNATURE'] : '';
        $event = isset($_SERVER['HTTP_X_LOMI_EVENT']) ? (string) $_SERVER['HTTP_X_LOMI_EVENT'] : '';

        /** @var Lomi $module */
        $module = $this->module;
        $secret = trim($module->getWebhookSecret());

        if ($signature === '' || $secret === '') {
            http_response_code(401);
            exit;
        }

        $expected = hash_hmac('sha256', $raw, $secret);
        if (!hash_equals($expected, $signature)) {
            http_response_code(401);
            exit;
        }

        if ($event !== 'PAYMENT_SUCCEEDED') {
            http_response_code(200);
            exit;
        }

        $payload = json_decode($raw);
        $data = (is_object($payload) && isset($payload->data)) ? $payload->data : null;
        if (!is_object($data)) {
            http_response_code(400);
            exit;
        }

        $cartId = 0;
        if (isset($data->metadata->ps_cart_id)) {
            $cartId = (int) $data->metadata->ps_cart_id;
        }
        if (!$cartId && !empty($data->checkout_session_id)) {
            $cartId = (int) $module->getCartIdByCheckoutSessionId((string) $data->checkout_session_id);
        }

        if (!$cartId) {
            http_response_code(200);
            exit;
        }

        $cart = new Cart($cartId);
        if (!Validate::isLoadedObject($cart) || (int) $cart->id_customer === 0) {
            http_response_code(200);
            exit;
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            http_response_code(200);
            exit;
        }

        $sessionId = isset($data->checkout_session_id) ? (string) $data->checkout_session_id : '';
        if ($sessionId === '') {
            http_response_code(200);
            exit;
        }

        $resp = $module->getApiClient()->getCheckoutSession($sessionId);
        if (empty($resp['ok']) || empty($resp['data'])) {
            http_response_code(200);
            exit;
        }

        $module->validateOrderIfSessionValid($cart, $customer, $resp['data']);

        http_response_code(200);
        exit;
    }
}
