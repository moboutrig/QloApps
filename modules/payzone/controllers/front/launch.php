<?php

class PayzoneLaunchModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_delivery);
        $currency = new Currency($cart->id_currency);

        $merchantAccount = Configuration::get('PAYZONE_MERCHANT_ACCOUNT');
        $paywallSecretKey = Configuration::get('PAYZONE_SECRET_KEY');
        $paywallUrl = Configuration::get('PAYZONE_URL');

        $payload = array(
            'merchantAccount'      => $merchantAccount,
            'timestamp'       => time(),
            'skin'        => 'vps-1-vue', // fixed value

            'customerId'      => $customer->id,
            'customerCountry' => $address->country,
            'customerLocale' => $this->context->language->iso_code,

            'chargeId'        => $cart->id . '_' . time(),
            'orderId'         => $cart->id,
            'price'           => $cart->getOrderTotal(true, Cart::BOTH),
            'currency'        => $currency->iso_code,
            'description'     => 'Order from ' . Configuration::get('PS_SHOP_NAME'),

            'mode' => 'DEEP_LINK',
            'paymentMethod' => 'CREDIT_CARD',
            'showPaymentProfiles' => 'false',
            'callbackUrl' => $this->context->link->getModuleLink('payzone', 'callback', array(), true),
            'successUrl' => $this->context->link->getModuleLink('payzone', 'success', array('id_cart' => $cart->id, 'key' => $customer->secure_key), true),
            'failureUrl' => $this->context->link->getModuleLink('payzone', 'failure', array(), true),
            'cancelUrl' => $this->context->link->getPageLink('order', true),
        );

        $json_payload = json_encode($payload);
        $signature = hash('sha256', $paywallSecretKey . $json_payload);

        $this->context->smarty->assign(array(
            'paywallUrl' => $paywallUrl,
            'payload' => $json_payload,
            'signature' => $signature,
        ));

        $this->setTemplate('module:payzone/views/templates/front/launch.tpl');
    }
}
