<?php
/**
 * lomi. — PrestaShop payment module (hosted checkout sessions).
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/LomiApiClient.php';
require_once __DIR__ . '/classes/CheckoutBranding.php';

class Lomi extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'lomi';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'lomi.';
        $this->controllers = array('payment', 'validation', 'callback', 'webhook', 'abandon');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('lomi.', array(), 'Modules.Lomi.Admin');
        $this->description = $this->trans('Accept payments via lomi. hosted checkout.', array(), 'Modules.Lomi.Admin');
        $this->confirmUninstall = $this->trans('Remove lomi. configuration and data?', array(), 'Modules.Lomi.Admin');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', array(), 'Modules.Lomi.Admin');
        }
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('actionFrontControllerSetMedia')
            || !$this->installDb()
            || !$this->installOrderState()
        ) {
            return false;
        }

        if (!Configuration::hasKey('LOMI_MODE')) {
            Configuration::updateValue('LOMI_MODE', 1);
        }

        return true;
    }

    /**
     * Register checkout branding stylesheet on order (checkout) page.
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        if (!$this->active || !isset($this->context->controller->php_self)) {
            return;
        }

        if ($this->context->controller->php_self !== 'order') {
            return;
        }

        $this->context->controller->registerStylesheet(
            'module-lomi-checkout-branding',
            'modules/' . $this->name . '/views/css/checkout-branding.css',
            array(
                'media' => 'all',
                'priority' => 200,
            )
        );

        $cart = $this->context->cart;
        $customer = $this->context->customer;
        if (!Validate::isLoadedObject($cart) || (int) $cart->id_customer === 0 || !Validate::isLoadedObject($customer)) {
            return;
        }

        $abandonUrl = $this->context->link->getModuleLink(
            $this->name,
            'abandon',
            array(
                'id_cart' => (int) $cart->id,
                'key' => $customer->secure_key,
                'ajax' => 1,
            ),
            true
        );

        Media::addJsDef(array(
            'lomiCheckoutParams' => array(
                'storageKey' => 'ps_lomi_checkout_redirect',
                'abandonUrl' => $abandonUrl,
            ),
        ));

        $this->context->controller->registerJavascript(
            'module-lomi-checkout-abandon',
            'modules/' . $this->name . '/views/js/checkout-abandon.js',
            array(
                'position' => 'bottom',
                'priority' => 210,
            )
        );
    }

    /**
     * @return LomiCheckoutBranding
     */
    protected function getCheckoutBranding()
    {
        return new LomiCheckoutBranding($this);
    }

    /**
     * @return string
     */
    protected function renderCheckoutBranding()
    {
        $branding = $this->getCheckoutBranding();
        $this->context->smarty->assign(
            array(
                'lomi_pay_with_image_url' => $branding->getPayWithImageUrl(),
                'lomi_payment_icons' => $branding->getPaymentIcons(),
            )
        );

        return $this->fetch('module:lomi/views/templates/hook/branding.tpl');
    }

    public function uninstall()
    {
        $this->uninstallDb();

        Configuration::deleteByName('LOMI_TEST_SECRETKEY');
        Configuration::deleteByName('LOMI_LIVE_SECRETKEY');
        Configuration::deleteByName('LOMI_TEST_PUBLICKEY');
        Configuration::deleteByName('LOMI_LIVE_PUBLICKEY');
        Configuration::deleteByName('LOMI_MODE');
        Configuration::deleteByName('LOMI_TEST_WEBHOOK_SECRET');
        Configuration::deleteByName('LOMI_LIVE_WEBHOOK_SECRET');
        Configuration::deleteByName('PS_OS_LOMI');

        return parent::uninstall();
    }

    protected function installDb()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'lomi_checkout` (
            `id_cart` INT UNSIGNED NOT NULL,
            `checkout_session_id` VARCHAR(64) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_cart`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    protected function uninstallDb()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'lomi_checkout`');
    }

    protected function installOrderState()
    {
        if (Configuration::get('PS_OS_LOMI')) {
            return true;
        }

        $newState = new OrderState();
        $newState->send_email = true;
        $newState->module_name = $this->name;
        $newState->invoice = true;
        $newState->color = '#04b404';
        $newState->unremovable = false;
        $newState->logable = true;
        $newState->delivery = false;
        $newState->hidden = false;
        $newState->shipped = false;
        $newState->paid = true;
        $newState->delete = false;

        $languages = Language::getLanguages(true);
        foreach ($languages as $lang) {
            $newState->name[(int) $lang['id_lang']] = $this->trans('Paid via lomi.', array(), 'Modules.Lomi.Admin');
        }
        $newState->template = 'payment';

        if (!$newState->add()) {
            return false;
        }
        Configuration::updateValue('PS_OS_LOMI', (int) $newState->id);

        return true;
    }

    /**
     * @return LomiApiClient
     */
    public function getApiClient()
    {
        $testMode = (int) Configuration::get('LOMI_MODE') === 1;
        $secret = $testMode
            ? Configuration::get('LOMI_TEST_SECRETKEY')
            : Configuration::get('LOMI_LIVE_SECRETKEY');

        return new LomiApiClient($testMode, (string) $secret);
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        $testMode = (int) Configuration::get('LOMI_MODE') === 1;

        return $testMode
            ? (string) Configuration::get('LOMI_TEST_SECRETKEY')
            : (string) Configuration::get('LOMI_LIVE_SECRETKEY');
    }

    /**
     * @return string
     */
    public function getWebhookSecret()
    {
        $testMode = (int) Configuration::get('LOMI_MODE') === 1;

        return $testMode
            ? (string) Configuration::get('LOMI_TEST_WEBHOOK_SECRET')
            : (string) Configuration::get('LOMI_LIVE_WEBHOOK_SECRET');
    }

    /**
     * @param Cart $cart
     *
     * @return int
     */
    public function getAmountMinorUnits(Cart $cart)
    {
        $currency = new Currency((int) $cart->id_currency);
        $iso = strtoupper((string) $currency->iso_code);
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        if ($iso === 'XOF') {
            return (int) round($total);
        }

        $decimals = 2;
        if (property_exists($currency, 'precision')) {
            $decimals = (int) $currency->precision;
        }

        return (int) round($total * pow(10, $decimals));
    }

    /**
     * @param int $idCart
     *
     * @return bool
     */
    public function saveCheckoutSessionForCart($idCart, $checkoutSessionId)
    {
        $idCart = (int) $idCart;
        $checkoutSessionId = pSQL($checkoutSessionId);
        $now = date('Y-m-d H:i:s');

        return Db::getInstance()->execute(
            'REPLACE INTO `' . _DB_PREFIX_ . 'lomi_checkout` (`id_cart`, `checkout_session_id`, `date_add`)
             VALUES (' . $idCart . ', \'' . $checkoutSessionId . '\', \'' . pSQL($now) . '\')'
        );
    }

    /**
     * @param int $idCart
     *
     * @return string|false
     */
    public function getCheckoutSessionIdForCart($idCart)
    {
        return Db::getInstance()->getValue(
            'SELECT `checkout_session_id` FROM `' . _DB_PREFIX_ . 'lomi_checkout` WHERE `id_cart`=' . (int) $idCart
        );
    }

    /**
     * @param int $sessionId
     *
     * @return int
     */
    public function getCartIdByCheckoutSessionId($sessionId)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT `id_cart` FROM `' . _DB_PREFIX_ . 'lomi_checkout` WHERE `checkout_session_id`=\'' . pSQL($sessionId) . '\''
        );
    }

    /**
     * @param int $idCart
     *
     * @return int
     */
    public function getOrderIdByCartId($idCart)
    {
        if (class_exists('Order') && method_exists('Order', 'getIdByCartId')) {
            return (int) Order::getIdByCartId((int) $idCart);
        }

        return (int) Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart`=' . (int) $idCart . ' ORDER BY `id_order` DESC'
        );
    }

    /**
     * Clear hosted checkout mapping when shopper abandons payment.
     *
     * @param int $idCart
     *
     * @return bool
     */
    public function abandonHostedCheckout($idCart)
    {
        $idCart = (int) $idCart;
        if ($idCart <= 0) {
            return false;
        }

        $orderId = $this->getOrderIdByCartId($idCart);
        if ($orderId) {
            $order = new Order($orderId);
            $paid = (int) Configuration::get('PS_OS_LOMI');
            if (Validate::isLoadedObject($order) && (int) $order->getCurrentState() === $paid) {
                return false;
            }
        }

        return Db::getInstance()->delete('lomi_checkout', 'id_cart=' . $idCart);
    }

    /**
     * @param Cart $cart
     * @param Customer $customer
     *
     * @return array|null payload array or null on failure
     */
    public function buildCreateCheckoutSessionBody(Cart $cart, Customer $customer)
    {
        $currency = new Currency((int) $cart->id_currency);
        $link = $this->context->link;

        $successUrl = $link->getModuleLink('lomi', 'callback', array(
            'id_cart' => (int) $cart->id,
            'key' => $customer->secure_key,
        ), true);

        $cancelUrl = $link->getModuleLink('lomi', 'abandon', array(
            'id_cart' => (int) $cart->id,
            'key' => $customer->secure_key,
        ), true);

        $phone = '';
        $addr = new Address((int) $cart->id_address_invoice);
        if (Validate::isLoadedObject($addr)) {
            $phone = trim((string) $addr->phone_mobile);
            if ($phone === '') {
                $phone = trim((string) $addr->phone);
            }
        }

        return array(
            'currency_code' => strtoupper($currency->iso_code),
            'amount' => $this->getAmountMinorUnits($cart),
            'integration_source' => 'prestashop',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $customer->email,
            'customer_name' => trim($customer->firstname . ' ' . $customer->lastname),
            'customer_phone' => $phone !== '' ? $phone : null,
            'require_billing_address' => false,
            'title' => sprintf('Cart %d', (int) $cart->id),
            'metadata' => array(
                'ps_cart_id' => (string) (int) $cart->id,
                'plugin' => 'presta-lomi',
            ),
        );
    }

    /**
     * @param Cart $cart
     * @param Customer $customer
     * @param object $session
     *
     * @return bool whether order was validated now or already paid
     */
    public function validateOrderIfSessionValid(Cart $cart, Customer $customer, $session)
    {
        if (!is_object($session)) {
            return false;
        }

        $osPaid = (int) Configuration::get('PS_OS_LOMI');
        if (!$osPaid) {
            return false;
        }

        $db = Db::getInstance();
        $lockName = 'lomi_order_cart_' . (int) $cart->id;
        $lockAcquired = (int) $db->getValue(
            'SELECT GET_LOCK(\'' . pSQL($lockName) . '\', 10)'
        );

        if ($lockAcquired !== 1) {
            return false;
        }

        try {
            $orderId = $this->getOrderIdByCartId((int) $cart->id);
            if ($orderId) {
                $order = new Order($orderId);
                if ((int) $order->getCurrentState() === $osPaid) {
                    return true;
                }
            }

            $status = strtolower((string) (isset($session->status) ? $session->status : ''));
            if ($status !== 'completed') {
                return false;
            }

            $expectedMinor = $this->getAmountMinorUnits($cart);
            $paidMinor = isset($session->amount) ? (int) $session->amount : 0;
            if ($paidMinor !== $expectedMinor) {
                return false;
            }

            $currency = new Currency((int) $cart->id_currency);
            $sessCur = isset($session->currency_code) ? strtoupper((string) $session->currency_code) : '';
            if ($sessCur !== '' && $sessCur !== strtoupper($currency->iso_code)) {
                return false;
            }

            $extraVars = array(
                'transaction_id' => isset($session->checkout_session_id) ? (string) $session->checkout_session_id : '',
                'payment_method' => 'lomi.',
            );

            $totalMajor = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $ref = isset($session->checkout_session_id) ? (string) $session->checkout_session_id : '';

            $this->validateOrder(
                (int) $cart->id,
                $osPaid,
                $totalMajor,
                $this->displayName,
                $this->trans('lomi. checkout session: ', array(), 'Modules.Lomi.Shop') . $ref,
                $extraVars,
                (int) $cart->id_currency,
                false,
                $customer->secure_key
            );

            return true;
        } finally {
            $db->getValue(
                'SELECT RELEASE_LOCK(\'' . pSQL($lockName) . '\')'
            );
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('LOMI_TEST_SECRETKEY', Tools::getValue('LOMI_TEST_SECRETKEY'));
            Configuration::updateValue('LOMI_TEST_PUBLICKEY', Tools::getValue('LOMI_TEST_PUBLICKEY'));
            Configuration::updateValue('LOMI_LIVE_SECRETKEY', Tools::getValue('LOMI_LIVE_SECRETKEY'));
            Configuration::updateValue('LOMI_LIVE_PUBLICKEY', Tools::getValue('LOMI_LIVE_PUBLICKEY'));
            Configuration::updateValue('LOMI_MODE', (int) Tools::getValue('LOMI_MODE'));
            Configuration::updateValue('LOMI_TEST_WEBHOOK_SECRET', Tools::getValue('LOMI_TEST_WEBHOOK_SECRET'));
            Configuration::updateValue('LOMI_LIVE_WEBHOOK_SECRET', Tools::getValue('LOMI_LIVE_WEBHOOK_SECRET'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->context->smarty->assign(
            array(
                'lomi_webhook_url' => $this->context->link->getModuleLink('lomi', 'webhook', array(), true),
                'lomi_module_logo' => __PS_BASE_URI__ . 'modules/' . $this->name . '/views/img/pay-with-lomi.webp',
            )
        );
        $this->_html .= $this->display(__FILE__, 'views/templates/hook/infos.tpl');
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return array();
        }

        if (!$this->checkCurrency($params['cart']) || !$this->checkCurrencyLomi($params['cart'])) {
            return array();
        }

        if (trim($this->getSecretKey()) === '') {
            return array();
        }

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->trans('Pay with lomi.', array(), 'Modules.Lomi.Shop'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->renderCheckoutBranding())
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/pay-with-lomi.webp'));

        return array($newOption);
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }

        $state = $params['order']->getCurrentState();
        $reference = Tools::getValue('reference');
        if ($reference === '' || $reference === null) {
            $reference = $params['order']->reference;
        }
        $paidState = (int) Configuration::get('PS_OS_LOMI');

        if (in_array((int) $state, array($paidState, (int) Configuration::get('PS_OS_OUTOFSTOCK'), (int) Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')), true)) {
            $this->smarty->assign(array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'status' => 'ok',
                'reference' => $reference,
                'contact_url' => $this->context->link->getPageLink('contact', true),
            ));
        } else {
            $this->smarty->assign(array(
                'status' => 'failed',
                'contact_url' => $this->context->link->getPageLink('contact', true),
            ));
        }

        return $this->fetch('module:lomi/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Lomi checkout supports XOF, USD, EUR.
     *
     * @param Cart $cart
     *
     * @return bool
     */
    public function checkCurrencyLomi($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $iso = strtoupper($currency_order->iso_code);
        $allowed = array('XOF', 'USD', 'EUR');

        return in_array($iso, $allowed, true);
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('lomi. settings', array(), 'Modules.Lomi.Admin'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Test mode', array(), 'Modules.Lomi.Admin'),
                        'name' => 'LOMI_MODE',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', array(), 'Admin.Global')),
                            array('id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', array(), 'Admin.Global')),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Test secret key', array(), 'Modules.Lomi.Admin'),
                        'name' => 'LOMI_TEST_SECRETKEY',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Test public key (optional)', array(), 'Modules.Lomi.Admin'),
                        'name' => 'LOMI_TEST_PUBLICKEY',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Test webhook secret', array(), 'Modules.Lomi.Admin'),
                        'name' => 'LOMI_TEST_WEBHOOK_SECRET',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Live secret key', array(), 'Modules.Lomi.Admin'),
                        'name' => 'LOMI_LIVE_SECRETKEY',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Live public key (optional)', array(), 'Modules.Lomi.Admin'),
                        'name' => 'LOMI_LIVE_PUBLICKEY',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Live webhook secret', array(), 'Modules.Lomi.Admin'),
                        'name' => 'LOMI_LIVE_WEBHOOK_SECRET',
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'LOMI_TEST_SECRETKEY' => Tools::getValue('LOMI_TEST_SECRETKEY', Configuration::get('LOMI_TEST_SECRETKEY')),
            'LOMI_TEST_PUBLICKEY' => Tools::getValue('LOMI_TEST_PUBLICKEY', Configuration::get('LOMI_TEST_PUBLICKEY')),
            'LOMI_LIVE_SECRETKEY' => Tools::getValue('LOMI_LIVE_SECRETKEY', Configuration::get('LOMI_LIVE_SECRETKEY')),
            'LOMI_LIVE_PUBLICKEY' => Tools::getValue('LOMI_LIVE_PUBLICKEY', Configuration::get('LOMI_LIVE_PUBLICKEY')),
            'LOMI_MODE' => (int) Tools::getValue('LOMI_MODE', Configuration::get('LOMI_MODE') === false ? 1 : Configuration::get('LOMI_MODE')),
            'LOMI_TEST_WEBHOOK_SECRET' => Tools::getValue('LOMI_TEST_WEBHOOK_SECRET', Configuration::get('LOMI_TEST_WEBHOOK_SECRET')),
            'LOMI_LIVE_WEBHOOK_SECRET' => Tools::getValue('LOMI_LIVE_WEBHOOK_SECRET', Configuration::get('LOMI_LIVE_WEBHOOK_SECRET')),
        );
    }
}
