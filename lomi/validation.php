<?php
/**
 * @deprecated — use module front controller validation
 */
include dirname(__FILE__) . '/../../config/config.inc.php';
include dirname(__FILE__) . '/../../header.php';
include dirname(__FILE__) . '/../../init.php';

$context = Context::getContext();
$cart = $context->cart;
$lomi = Module::getInstanceByName('lomi');

if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$lomi->active) {
    Tools::redirect('index.php?controller=order&step=1');
}

$authorized = false;
foreach (Module::getPaymentModules() as $module) {
    if ($module['name'] == 'lomi') {
        $authorized = true;
        break;
    }
}
if (!$authorized) {
    die($lomi->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Lomi.Shop'));
}

$customer = new Customer((int) $cart->id_customer);
if (!Validate::isLoadedObject($customer)) {
    Tools::redirect('index.php?controller=order&step=1');
}

Tools::redirect(Context::getContext()->link->getModuleLink('lomi', 'validation', array(), true));
