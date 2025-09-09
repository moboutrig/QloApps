<?php

class PayzoneFailureModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Add an error message to be displayed
        $this->errors[] = $this->module->l('Payment was not successful. Please try again or contact support.', 'failure');

        // Redirect to the order page
        $this->redirectWithNotifications('index.php?controller=order&step=1');
    }
}
