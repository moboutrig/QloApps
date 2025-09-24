<?php
class AdminTaxesController extends AdminTaxesControllerCore
{
    public function renderForm()
    {
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Taxes'),
                'icon' => 'icon-money'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'required' => true,
                    'lang' => true,
                    'hint' => $this->l('Tax name to display in carts and on invoices (e.g. "VAT").').' - '.$this->l('Invalid characters').' <>;=#{}'
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Tax Type'),
                    'name' => 'type',
                    'required' => true,
                    'options' => array(
                        'query' => array(
                            array(
                                'id' => Tax::TAX_TYPE_PERCENT,
                                'name' => $this->l('Percentage')
                            ),
                            array(
                                'id' => Tax::TAX_TYPE_PER_PERSON_PER_NIGHT,
                                'name' => $this->l('Per Person Per Night')
                            )
                        ),
                        'id' => 'id',
                        'name' => 'name'
                    ),
                    'hint' => $this->l('Select the type of tax.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Rate'),
                    'name' => 'rate',
                    'maxlength' => 6,
                    'required' => true,
                    'hint' => $this->l('Format: XX.XX or XX.XXX (e.g. 19.60 or 13.925)').' - '.$this->l('Invalid characters').' <>;=#{}'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Amount'),
                    'name' => 'amount',
                    'required' => true,
                    'hint' => $this->l('The fixed amount of the tax.')
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable'),
                    'name' => 'active',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                )
            ),
            'submit' => array(
                'title' => $this->l('Save')
            ),
            'buttons' => array(
                'save-and-stay' => array(
                    'title' => $this->l('Save and stay'),
                    'name' => 'submitAdd'.$this->table.'AndStay',
                    'type' => 'submit',
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-save',
                ),
            ),
        );

        $this->addJs(_PS_JS_DIR_.'jquery/plugins/jquery.chosen.js');
        $this->addCss(_PS_CSS_DIR_.'jquery-ui.css');
        $this->addJs(_PS_JS_DIR_.'jquery/ui/jquery.ui.core.min.js');
        $this->addJs(_PS_JS_DIR_.'jquery/ui/jquery.ui.widget.min.js');
        $this->addJs(_PS_JS_DIR_.'jquery/ui/jquery.ui.mouse.min.js');
        $this->addJs(_PS_JS_DIR_.'jquery/ui/jquery.ui.slider.min.js');
        $this->addJs(_PS_JS_DIR_.'jquery/ui/jquery.ui.datepicker.min.js');

        $this->addJS(_PS_JS_DIR_.'admin/themes.js');
        $this->addJs(_PS_JS_DIR_.'jquery/plugins/jquery.typewatch.js');

        $this->getFieldsValues();

        $this->context->controller->addJS(
            _MODULE_DIR_.'hotelreservationsystem/views/js/admin/tax_form.js'
        );

        return parent::renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitAdd'.$this->table) || Tools::isSubmit('submitAdd'.$this->table.'AndStay') || Tools::isSubmit('submitUpdate'.$this->table)) {
            $this->validateRules();
            if (count($this->errors)) {
                $this->display = 'add';
                return false;
            }
            if (Tools::getValue('type') == Tax::TAX_TYPE_PERCENT) {
                if (!Tools::getValue('rate') || Tools::getValue('rate') <= 0) {
                    $this->errors[] = Tools::displayError('The rate is required for percentage-based taxes.');
                }
                 $_POST['amount'] = 0;
            } elseif (Tools::getValue('type') == Tax::TAX_TYPE_PER_PERSON_PER_NIGHT) {
                if (!Tools::getValue('amount') || Tools::getValue('amount') <= 0) {
                    $this->errors[] = Tools::displayError('The amount is required for fixed taxes.');
                }
                $_POST['rate'] = 0;
            }

            if (count($this->errors)) {
                $this->display = 'add';
                return false;
            }
        }
        return parent::postProcess();
    }
}
