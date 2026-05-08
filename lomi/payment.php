<?php
/**
 * @deprecated — use module front controller payment
 */
$useSSL = true;
require dirname(__FILE__) . '/../../config/config.inc.php';
Tools::displayFileAsDeprecated();
$controller = new FrontController();
$controller->init();
Tools::redirect(Context::getContext()->link->getModuleLink('lomi', 'payment'));
