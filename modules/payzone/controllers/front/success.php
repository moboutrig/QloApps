<?php

class PayzoneSuccessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(array(
            'message' => 'Thank you for your order. We are currently processing your payment and you will receive a confirmation email shortly.'
        ));

        $this->setTemplate('module:payzone/views/templates/front/success.tpl');
    }
}
