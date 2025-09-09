<?php

class PayzoneCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $notificationKey = Configuration::get('PAYZONE_NOTIFICATION_KEY');
        $input = file_get_contents('php://input');
        $signature = hash_hmac('sha256', $input, $notificationKey);

        // In PrestaShop, headers are prefixed with HTTP_
        $received_signature = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'];

        if (strcasecmp($signature, $received_signature) == 0) {
            $input_array = json_decode($input, true);

            if ($input_array['status'] == 'CHARGED') {
                $transaction_data = null;
                foreach ($input_array['transactions'] as $transaction) {
                    if ($transaction['state'] == 'APPROVED') {
                        $transaction_data = $transaction;
                        break;
                    }
                }

                if ($transaction_data && $transaction_data['resultCode'] === 0) {
                    $cart_id = (int)$input_array['orderId'];
                    $cart = new Cart($cart_id);
                    $customer = new Customer($cart->id_customer);

                    if (!Validate::isLoadedObject($cart) || !Validate::isLoadedObject($customer)) {
                         $this->jsonResponse(['status' => 'KO', 'message' => 'Cart or Customer not found']);
                    }

                    $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

                    $this->module->validateOrder(
                        $cart->id,
                        Configuration::get('PS_OS_PAYMENT'),
                        $total,
                        $this->module->displayName,
                        NULL,
                        array('transaction_id' => $transaction_data['id']),
                        (int)$cart->id_currency,
                        false,
                        $customer->secure_key
                    );

                    $this->jsonResponse(['status' => 'OK', 'message' => 'Status recorded successfully']);
                }
            }

            // Handle other statuses if necessary
            $this->jsonResponse(['status' => 'KO', 'message' => 'Payment not approved or status not charged.']);

        } else {
            $this->jsonResponse(['status' => 'KO', 'message' => 'Error signature']);
        }
    }

    protected function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
