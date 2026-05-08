<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Return URL after lomi. hosted checkout — verifies session then validates order.
 */
class LomiCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $idCart = (int) Tools::getValue('id_cart');
        $key = (string) Tools::getValue('key');

        $cart = new Cart($idCart);
        if (!Validate::isLoadedObject($cart) || (int) $cart->id_customer === 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer) || $customer->secure_key !== $key) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        /** @var Lomi $module */
        $module = $this->module;

        $sessionId = $module->getCheckoutSessionIdForCart($idCart);
        if (!$sessionId) {
            Tools::redirect($this->context->link->getPageLink('order', true, null, array('step' => 3, 'has_error' => 1)));
        }

        $resp = $module->getApiClient()->getCheckoutSession((string) $sessionId);
        if (empty($resp['ok']) || empty($resp['data'])) {
            $err = isset($resp['error']) ? $resp['error'] : '';
            PrestaShopLogger::addLog('lomi. callback: could not fetch checkout session: ' . $err, 3);
            Tools::redirect($this->context->link->getPageLink('order', true, null, array('step' => 3, 'has_error' => 1)));
        }

        $session = $resp['data'];

        $orderId = $module->getOrderIdByCartId($idCart);
        if ($orderId) {
            $order = new Order((int) $orderId);
            $paid = (int) Configuration::get('PS_OS_LOMI');
            if ((int) $order->getCurrentState() === $paid) {
                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart=' . (int) $idCart . '&id_module=' . (int) $module->id . '&id_order=' . (int) $orderId . '&key=' . urlencode($customer->secure_key)
                );
            }
        }

        if ($module->validateOrderIfSessionValid($cart, $customer, $session)) {
            $newOrderId = (int) $module->currentOrder;
            if (!$newOrderId) {
                $newOrderId = $module->getOrderIdByCartId($idCart);
            }
            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart=' . (int) $idCart . '&id_module=' . (int) $module->id . '&id_order=' . (int) $newOrderId . '&key=' . urlencode($customer->secure_key)
            );
        }

        Tools::redirect($this->context->link->getPageLink('order', true, null, array('step' => 3, 'has_error' => 1)));
    }
}
