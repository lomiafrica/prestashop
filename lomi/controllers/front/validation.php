<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Creates a Lomi checkout session and redirects to hosted checkout.
 */
class LomiValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === 'lomi') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Lomi.Shop'));
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        /** @var Lomi $module */
        $module = $this->module;
        $body = $module->buildCreateCheckoutSessionBody($cart, $customer);
        foreach ($body as $k => $v) {
            if ($v === null || $v === '') {
                unset($body[$k]);
            }
        }

        $result = $module->getApiClient()->createCheckoutSession($body);
        if (
            empty($result['ok'])
            || empty($result['data'])
            || empty($result['data']->checkout_url)
            || empty($result['data']->checkout_session_id)
        ) {
            $err = isset($result['error']) ? $result['error'] : 'unknown';
            PrestaShopLogger::addLog('lomi. create checkout session failed: ' . $err, 3);
            Tools::redirect($this->context->link->getPageLink('order', true, null, array('step' => 3, 'has_error' => 1)));
        }

        $module->saveCheckoutSessionForCart((int) $cart->id, (string) $result['data']->checkout_session_id);

        Tools::redirect((string) $result['data']->checkout_url);
    }
}
