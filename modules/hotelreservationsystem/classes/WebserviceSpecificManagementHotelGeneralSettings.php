<?php
/**
* 2010-2023 Webkul.
*
* NOTICE OF LICENSE
*
* All right is reserved,
* Please go through this link for complete license : https://store.webkul.com/license.html
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to newer
* versions in the future. If you wish to customize this module for your
* needs please refer to https://store.webkul.com/customisation-guidelines/ for more information.
*
*  @author    Webkul IN <support@webkul.com>
*  @copyright 2010-2023 Webkul IN
*  @license   https://store.webkul.com/license.html
*/

class WebserviceSpecificManagementHotelGeneralSettings implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;

    /** @var WebserviceRequest */
    protected $wsObject;

    protected $output;

    /**
     * @param WebserviceOutputBuilderCore $obj
     * @return WebserviceSpecificManagementInterface
     */
    public function setObjectOutput(WebserviceOutputBuilderCore $obj)
    {
        $this->objOutput = $obj;
        return $this;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function setWsObject(WebserviceRequestCore $obj)
    {
        $this->wsObject = $obj;
        return $this;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function manage()
    {
        if ($this->wsObject->method == 'GET') {
            $settings = array();
            $languages = Language::getLanguages(true);
            foreach ($languages as $lang) {
                $settings['hotel_name'][$lang['id_lang']] = Configuration::get('WK_HTL_CHAIN_NAME', $lang['id_lang']);
                $settings['hotel_tag_line'][$lang['id_lang']] = Configuration::get('WK_HTL_TAG_LINE', $lang['id_lang']);
                $settings['hotel_short_description'][$lang['id_lang']] = Configuration::get('WK_HTL_SHORT_DESC', $lang['id_lang']);
            }
            $settings['website_launch_year'] = Configuration::get('WK_HTL_ESTABLISHMENT_YEAR');

            $headerImage = Configuration::get('WK_HOTEL_HEADER_IMAGE');
            if ($headerImage && file_exists(_PS_ROOT_DIR_ . '/img/' . $headerImage)) {
                $domain = Tools::getShopDomain(true);
                $baseUri = __PS_BASE_URI__;
                $settings['header_background_image'] = $domain.$baseUri.'img/'.$headerImage;
            } else {
                $settings['header_background_image'] = false;
            }

            $logoFilename = Configuration::get('PS_LOGO');
            if ($logoFilename && file_exists(_PS_ROOT_DIR_ . '/img/' . $logoFilename)) {
                $settings['shop_logo'] = Context::getContext()->link->getMediaLink(_PS_IMG_.$logoFilename);
            } else {
                $settings['shop_logo'] = false;
            }

            $settings['display_properties_link_in_header'] = Configuration::get('WK_DISPLAY_PROPERTIES_LINK_IN_HEADER');
            $settings['display_contact_page_hotel_list'] = Configuration::get('WK_DISPLAY_CONTACT_PAGE_HOTEL_LIST');

            $settings['support_phone_number'] = Configuration::get('WK_CUSTOMER_SUPPORT_PHONE_NUMBER');
            $settings['support_email'] = Configuration::get('WK_CUSTOMER_SUPPORT_EMAIL');

            $settings['website_name'] = Configuration::get('PS_SHOP_NAME');
            $settings['website_email'] = Configuration::get('PS_SHOP_EMAIL');
            $settings['phone'] = Configuration::get('PS_SHOP_PHONE');
            $settings['address_line_1'] = Configuration::get('PS_SHOP_ADDR1');
            $settings['address_line_2'] = Configuration::get('PS_SHOP_ADDR2');
            $settings['zip_postal_code'] = Configuration::get('PS_SHOP_CODE');
            $settings['city'] = Configuration::get('PS_SHOP_CITY');

            $countryId = Configuration::get('PS_SHOP_COUNTRY_ID');
            $settings['country_id'] = $countryId;
            if ($countryId) {
                $country = new Country($countryId, Context::getContext()->language->id);
                $settings['country_name'] = $country->name;
            } else {
                $settings['country_name'] = null;
            }

            $stateId = Configuration::get('PS_SHOP_STATE_ID');
            $settings['state_id'] = $stateId;
            if ($stateId) {
                $state = new State($stateId);
                $settings['state_name'] = $state->name;
            } else {
                $settings['state_name'] = null;
            }

            if (get_class($this->objOutput->getObjectRender()) == 'WebserviceOutputJSON') {
                $settings['display_properties_link_in_header'] = (bool)$settings['display_properties_link_in_header'];
                $settings['display_contact_page_hotel_list'] = (bool)$settings['display_contact_page_hotel_list'];
                $this->output = json_encode(['hotel_general_settings' => $settings]);
            } else { // XML
                $this->output .= $this->objOutput->getObjectRender()->renderNodeHeader('hotel_general_settings', array());
                foreach ($settings as $key => $value) {
                    $field = ['sqlId' => $key, 'value' => $value];
                    if (is_array($value)) {
                        $field['i18n'] = true;
                    }
                    $this->output .= $this->objOutput->getObjectRender()->renderField($field);
                }
                $this->output .= $this->objOutput->getObjectRender()->renderNodeFooter('hotel_general_settings', array());
                $this->output = $this->objOutput->getObjectRender()->overrideContent($this->output);
            }
        } else {
            $this->wsObject->setError(405, 'Method '.$this->wsObject->method.' is not valid for this resource', 23);
        }
    }

    public function getContent()
    {
        return $this->output;
    }
}
