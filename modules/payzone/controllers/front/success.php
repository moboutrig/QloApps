<?php

class PayzoneSuccessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $cart_id = Tools::getValue('id_cart');
        $secure_key = Tools::getValue('key');

        $cart = new Cart($cart_id);
        $customer = new Customer($cart->id_customer);

        if ($secure_key == $customer->secure_key) {
            $order_id = Order::getOrderByCartId($cart_id);
            $module_id = $this->module->id;
            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart_id.'&id_module='.$module_id.'&id_order='.$order_id.'&key='.$secure_key);
        } else {
            Tools::redirect('index.php?controller=order&step=1');
        }
    }
}
