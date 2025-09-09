<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Payzone extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'payzone';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Jules';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Payzone');
        $this->description = $this->l('Accept payments through Payzone.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        // Set default configuration
        Configuration::updateValue('PAYZONE_MERCHANT_ACCOUNT', 'Next_APP');
        Configuration::updateValue('PAYZONE_SECRET_KEY', 'M4KjfPffvXfQ88qPw');
        Configuration::updateValue('PAYZONE_NOTIFICATION_KEY', 'x5Lf5sDhFBrA7oWW');
        Configuration::updateValue('PAYZONE_URL', 'https://payment-sandbox.payzone.ma/pwthree/launch');

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        // Delete configuration
        Configuration::deleteByName('PAYZONE_MERCHANT_ACCOUNT');
        Configuration::deleteByName('PAYZONE_SECRET_KEY');
        Configuration::deleteByName('PAYZONE_NOTIFICATION_KEY');
        Configuration::deleteByName('PAYZONE_URL');

        return true;
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('PAYZONE_MERCHANT_ACCOUNT')) {
                $this->_postErrors[] = $this->l('Merchant Account is required.');
            }
            if (!Tools::getValue('PAYZONE_SECRET_KEY')) {
                $this->_postErrors[] = $this->l('Secret Key is required.');
            }
            if (!Tools::getValue('PAYZONE_NOTIFICATION_KEY')) {
                $this->_postErrors[] = $this->l('Notification Key is required.');
            }
            if (!Tools::getValue('PAYZONE_URL')) {
                $this->_postErrors[] = $this->l('Paywall URL is required.');
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('PAYZONE_MERCHANT_ACCOUNT', Tools::getValue('PAYZONE_MERCHANT_ACCOUNT'));
            Configuration::updateValue('PAYZONE_SECRET_KEY', Tools::getValue('PAYZONE_SECRET_KEY'));
            Configuration::updateValue('PAYZONE_NOTIFICATION_KEY', Tools::getValue('PAYZONE_NOTIFICATION_KEY'));
            Configuration::updateValue('PAYZONE_URL', Tools::getValue('PAYZONE_URL'));
        }
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Payzone Configuration'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Merchant Account'),
                        'name' => 'PAYZONE_MERCHANT_ACCOUNT',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secret Key'),
                        'name' => 'PAYZONE_SECRET_KEY',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Notification Key'),
                        'name' => 'PAYZONE_NOTIFICATION_KEY',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Paywall URL'),
                        'name' => 'PAYZONE_URL',
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PAYZONE_MERCHANT_ACCOUNT' => Tools::getValue('PAYZONE_MERCHANT_ACCOUNT', Configuration::get('PAYZONE_MERCHANT_ACCOUNT')),
            'PAYZONE_SECRET_KEY' => Tools::getValue('PAYZONE_SECRET_KEY', Configuration::get('PAYZONE_SECRET_KEY')),
            'PAYZONE_NOTIFICATION_KEY' => Tools::getValue('PAYZONE_NOTIFICATION_KEY', Configuration::get('PAYZONE_NOTIFICATION_KEY')),
            'PAYZONE_URL' => Tools::getValue('PAYZONE_URL', Configuration::get('PAYZONE_URL')),
        );
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
        ));
        return $this->display(__FILE__, 'payment.tpl');
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
}
