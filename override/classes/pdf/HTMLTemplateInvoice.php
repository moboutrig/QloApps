<?php
class HTMLTemplateInvoice extends HTMLTemplateInvoiceCore
{
    public function getContent()
    {
        $invoiceAddressPatternRules = json_decode(Configuration::get('PS_INVCE_INVOICE_ADDR_RULES'), true);
        $deliveryAddressPatternRules = json_decode(Configuration::get('PS_INVCE_DELIVERY_ADDR_RULES'), true);

        $invoice_address = new Address((int)$this->order->id_address_invoice);
        $country = new Country((int)$invoice_address->id_country);
        $formatted_invoice_address = AddressFormat::generateAddress($invoice_address, $invoiceAddressPatternRules, '<br />', ' ');

        $delivery_address = null;
        $formatted_delivery_address = '';
        if (isset($this->order->id_address_delivery) && $this->order->id_address_delivery) {
            $delivery_address = new Address((int)$this->order->id_address_delivery);
            $formatted_delivery_address = AddressFormat::generateAddress($delivery_address, $deliveryAddressPatternRules, '<br />', ' ');
        }

        $customer = new Customer((int)$this->order->id_customer);
        $carrier = new Carrier((int)$this->order->id_carrier);

        $order_details = $this->order_invoice->getProducts();

        $has_discount = false;
        foreach ($order_details as $id => &$order_detail) {
            if ($order_detail['reduction_amount_tax_excl'] > 0) {
                $has_discount = true;
                $order_detail['unit_price_tax_excl_before_specific_price'] = $order_detail['unit_price_tax_excl_including_ecotax'] + $order_detail['reduction_amount_tax_excl'];
            } elseif ($order_detail['reduction_percent'] > 0) {
                $has_discount = true;
                $order_detail['unit_price_tax_excl_before_specific_price'] = (100 * $order_detail['unit_price_tax_excl_including_ecotax']) / (100 - $order_detail['reduction_percent']);
            }

            $taxes = OrderDetail::getTaxListStatic($id);
            $tax_temp = array();
            foreach ($taxes as $tax) {
                $obj = new Tax($tax['id_tax']);
                $tax_temp[] = sprintf($this->l('%1$s%2$s%%'), ($obj->rate + 0), '&nbsp;');
            }

            $order_detail['order_detail_tax'] = $taxes;
            $order_detail['order_detail_tax_label'] = implode(', ', $tax_temp);
        }
        unset($tax_temp);
        unset($order_detail);

        if (Configuration::get('PS_PDF_IMG_INVOICE')) {
            foreach ($order_details as &$order_detail) {
                if ($order_detail['image'] != null) {
                    $name = 'product_mini_'.(int)$order_detail['product_id'].(isset($order_detail['product_attribute_id']) ? '_'.(int)$order_detail['product_attribute_id'] : '').'.jpg';
                    $path = _PS_PROD_IMG_DIR_.$order_detail['image']->getExistingImgPath().'.jpg';

                    $order_detail['image_tag'] = preg_replace(
                        '/\.*'.preg_quote(__PS_BASE_URI__, '/').'/',
                        _PS_ROOT_DIR_.DIRECTORY_SEPARATOR,
                        ImageManager::thumbnail($path, $name, 45, 'jpg', false),
                        1
                    );

                    if (file_exists(_PS_TMP_IMG_DIR_.$name)) {
                        $order_detail['image_size'] = getimagesize(_PS_TMP_IMG_DIR_.$name);
                    } else {
                        $order_detail['image_size'] = false;
                    }
                }
            }
            unset($order_detail);
        }

        $cart_rules = $this->order->getCartRules($this->order_invoice->id);
        $free_shipping = false;
        foreach ($cart_rules as $key => $cart_rule) {
            if ($cart_rule['free_shipping']) {
                $free_shipping = true;
                $cart_rules[$key]['value_tax_excl'] -= $this->order_invoice->total_shipping_tax_excl;
                $cart_rules[$key]['value'] -= $this->order_invoice->total_shipping_tax_incl;

                if ($cart_rules[$key]['value'] == 0) {
                    unset($cart_rules[$key]);
                }
            }
        }

        $product_taxes = 0;
        foreach ($this->order_invoice->getProductTaxesBreakdown($this->order) as $details) {
            $product_taxes += $details['total_amount'];
        }

        $product_discounts_tax_excl = $this->order_invoice->total_discount_tax_excl;
        $product_discounts_tax_incl = $this->order_invoice->total_discount_tax_incl;
        if ($free_shipping) {
            $product_discounts_tax_excl -= $this->order_invoice->total_shipping_tax_excl;
            $product_discounts_tax_incl -= $this->order_invoice->total_shipping_tax_incl;
        }

        $products_after_discounts_tax_excl = $this->order_invoice->total_products - $product_discounts_tax_excl;
        $products_after_discounts_tax_incl = $this->order_invoice->total_products_wt - $product_discounts_tax_incl;

        $shipping_tax_excl = $free_shipping ? 0 : $this->order_invoice->total_shipping_tax_excl;
        $shipping_tax_incl = $free_shipping ? 0 : $this->order_invoice->total_shipping_tax_incl;
        $shipping_taxes = $shipping_tax_incl - $shipping_tax_excl;

        $wrapping_taxes = $this->order_invoice->total_wrapping_tax_incl - $this->order_invoice->total_wrapping_tax_excl;

        $total_taxes = $this->order_invoice->total_paid_tax_incl - $this->order_invoice->total_paid_tax_excl;

        $footer = array(
            'products_before_discounts_tax_excl' => $this->order_invoice->total_products,
            'product_discounts_tax_excl' => $product_discounts_tax_excl,
            'products_after_discounts_tax_excl' => $products_after_discounts_tax_excl,
            'products_before_discounts_tax_incl' => $this->order_invoice->total_products_wt,
            'product_discounts_tax_incl' => $product_discounts_tax_incl,
            'products_after_discounts_tax_incl' => $products_after_discounts_tax_incl,
            'product_taxes' => $product_taxes,
            'shipping_tax_excl' => $shipping_tax_excl,
            'shipping_taxes' => $shipping_taxes,
            'shipping_tax_incl' => $shipping_tax_incl,
            'wrapping_tax_excl' => $this->order_invoice->total_wrapping_tax_excl,
            'wrapping_taxes' => $wrapping_taxes,
            'wrapping_tax_incl' => $this->order_invoice->total_wrapping_tax_incl,
            'ecotax_taxes' => $total_taxes - $product_taxes - $wrapping_taxes - $shipping_taxes,
            'total_taxes' => $total_taxes,
            'total_paid_tax_excl' => $this->order_invoice->total_paid_tax_excl,
            'total_paid_tax_incl' => $this->order_invoice->total_paid_tax_incl,
        );

        foreach ($footer as $key => $value) {
            $footer[$key] = Tools::ps_round($value, _PS_PRICE_COMPUTE_PRECISION_, $this->order->round_mode);
        }

        $round_type = null;
        switch ($this->order->round_type) {
        case Order::ROUND_TOTAL:
            $round_type = 'total';
            break;
        case Order::ROUND_LINE:
            $round_type = 'line';
            break;
        case Order::ROUND_ITEM:
            $round_type = 'item';
            break;
        default:
            $round_type = 'line';
            break;
        }

        $display_product_images = Configuration::get('PS_PDF_IMG_INVOICE');
        $tax_excluded_display = Group::getPriceDisplayMethod($customer->id_default_group);

        $layout = $this->computeLayout(array('has_discount' => $has_discount));

        $legal_free_text = Hook::exec('displayInvoiceLegalFreeText', array('order' => $this->order));
        if (!$legal_free_text) {
            $legal_free_text = Configuration::get('PS_INVOICE_LEGAL_FREE_TEXT', (int)Context::getContext()->language->id, null, (int)$this->order->id_shop);
        }
        $order_obj = new Order($this->order->id);

        $context = Context::getContext();

        $cart_htl_data = array();
        $service_product_data = array();
        $room_extra_demands = array();
        $room_additinal_services = array();
        $fee_products = array();
        $formattedHotelAddress = '';
        if (Module::isInstalled('hotelreservationsystem')) {
            $obj_htl_bk_dtl = new HotelBookingDetail();
            $objServiceProductOrderDetail = new ServiceProductOrderDetail();
            $objHotelBranchInfo = new HotelBranchInformation((int) HotelBookingDetail::getIdHotelByIdOrder($order_obj->id), $context->language->id);
            $invoiceAddressPatternRules['avoid'][] = 'lastname';
            if ($idHotelAddress = $objHotelBranchInfo->getHotelIdAddress()) {
                $objHotelAddress = new Address((int) $idHotelAddress);
                $objHotelAddress->firstname = $objHotelBranchInfo->hotel_name;
                $formattedHotelAddress = AddressFormat::generateAddress($objHotelAddress, $invoiceAddressPatternRules, '<br />', ' ');
            }

            $customer = new Customer($this->order->id_customer);
            if (!empty($order_details)) {
                $processed_product = array();
                $totalDemandsPriceTE = 0;
                $totalDemandsPriceTI = 0;
                foreach ($order_details as $type_key => $type_value) {
                    $product = new Product($type_value['product_id'], false, $context->language->id);
                    if ($product->price_addition_type == Product::PRICE_ADDITION_TYPE_INDEPENDENT) {
                        $fee_products[] = $type_value;
                        continue;
                    }

                    $processProuctKey = $type_value['product_id'].'_'.$type_value['selling_preference_type'].'_'.$type_value['id_order_detail'];
                    if (isset($processed_product[$processProuctKey])) {
                        continue;
                    }
                    $processed_product[$processProuctKey] = $type_value['product_id'];

                    if ($type_value['is_booking_product']) {
                        if ($display_product_images) {
                            $cover_image_arr = $product->getCover($type_value['product_id']);

                            if (!empty($cover_image_arr)) {
                                $coverImageObj = new Image($cover_image_arr['id_image']);
                                $cover_img = _PS_PROD_IMG_DIR_.$coverImageObj->getExistingImgPath().'.jpg';
                            } else {
                                $cover_img = _PS_PROD_IMG_DIR_.$context->language->iso_code.'-default-small_default.jpg';
                            }
                            $cart_htl_data[$type_key]['cover_img']    = $cover_img;
                        }

                        if ($type_value)
                        if (isset($customer->id)) {
                            $cart_obj = new Cart($this->order->id_cart);
                            $order_bk_data = $obj_htl_bk_dtl->getOnlyOrderBookingData($this->order->id, $cart_obj->id_guest, $type_value['product_id'], $customer->id);
                        } else {
                            $order_bk_data = $obj_htl_bk_dtl->getOnlyOrderBookingData($this->order->id, $customer->id_guest, $type_value['product_id'], 0);
                        }

                        $cart_htl_data[$type_key]['id_product'] = $type_value['product_id'];
                        $objBookingDemand = new HotelBookingDemands();
                        foreach ($order_bk_data as $data_k => $data_v) {
                            $date_join = strtotime($data_v['date_from']).strtotime($data_v['date_to']);

                            $order_details_obj = new OrderDetail($data_v['id_order_detail']);
                            $unit_price_tax_excl = 0;
                            $unit_price_tax_incl = 0;
                            $unit_price_tax_excl = $order_details_obj->unit_price_tax_excl;
                            $unit_price_tax_incl = $order_details_obj->unit_price_tax_incl;

                            $cart_htl_data[$type_key]['hotel_name'] = $data_v['hotel_name'];
                            $cart_htl_data[$type_key]['name'] = $order_details_obj->product_name;
                            $cart_htl_data[$type_key]['unit_price_tax_excl'] = $unit_price_tax_excl;
                            $cart_htl_data[$type_key]['unit_price_tax_incl'] = $unit_price_tax_incl;

                            if (isset($cart_htl_data[$type_key]['date_diff'][$date_join])) {
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['num_rm'] += 1;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['adults'] += $data_v['adults'];
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['children'] += $data_v['children'];

                                $num_days = $cart_htl_data[$type_key]['date_diff'][$date_join]['num_days'];
                                $var_quant = (int)$cart_htl_data[$type_key]['date_diff'][$date_join]['num_rm'];
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['id_room'] = $data_v['id_room'];
                            } else {
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['extra_demands_price_te'] = $objBookingDemand->getRoomTypeBookingExtraDemands(
                                    $order_obj->id,
                                    $type_value['product_id'],
                                    0,
                                    $data_v['date_from'],
                                    $data_v['date_to'],
                                    0,
                                    1,
                                    0,
                                    0,
                                    $type_value['id_order_detail']
                                );
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['extra_demands_price_ti'] = $objBookingDemand->getRoomTypeBookingExtraDemands(
                                    $order_obj->id,
                                    $type_value['product_id'],
                                    0,
                                    $data_v['date_from'],
                                    $data_v['date_to'],
                                    0,
                                    1,
                                    1,
                                    0,
                                    $type_value['id_order_detail']
                                );
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services'] = $objServiceProductOrderDetail->getRoomTypeServiceProducts(
                                    $order_obj->id,
                                    0,
                                    0,
                                    $data_v['id_product'],
                                    $data_v['date_from'],
                                    $data_v['date_to'],
                                    0
                                );

                                $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_ti'] = $objServiceProductOrderDetail->getRoomTypeServiceProducts(
                                    $order_obj->id,
                                    0,
                                    0,
                                    $data_v['id_product'],
                                    $data_v['date_from'],
                                    $data_v['date_to'],
                                    0,
                                    1,
                                    1
                                );
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_te'] = $objServiceProductOrderDetail->getRoomTypeServiceProducts(
                                    $order_obj->id,
                                    0,
                                    0,
                                    $data_v['id_product'],
                                    $data_v['date_from'],
                                    $data_v['date_to'],
                                    0,
                                    1,
                                    0
                                );

                                $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_ti'] = $objServiceProductOrderDetail->getRoomTypeServiceProducts(
                                    $order_obj->id,
                                    0,
                                    0,
                                    $data_v['id_product'],
                                    $data_v['date_from'],
                                    $data_v['date_to'],
                                    0,
                                    1,
                                    1,
                                    1,
                                    Product::PRICE_ADDITION_TYPE_WITH_ROOM
                                );
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_te'] = $objServiceProductOrderDetail->getRoomTypeServiceProducts(
                                    $order_obj->id,
                                    0,
                                    0,
                                    $data_v['id_product'],
                                    $data_v['date_from'],
                                    $data_v['date_to'],
                                    0,
                                    1,
                                    0,
                                    1,
                                    Product::PRICE_ADDITION_TYPE_WITH_ROOM
                                );

                                $num_days = HotelHelper::getNumberOfDays($data_v['date_from'], $data_v['date_to']);

                                $cart_htl_data[$type_key]['date_diff'][$date_join]['num_rm'] = 1;
                                $fullDate = (isset($context->controller->show_full_date) && $context->controller->show_full_date && (date('Y-m-d', strtotime($data_v['date_from'])) == date('Y-m-d', strtotime($data_v['date_to'])))) ? true : false;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['data_form'] = Tools::displayDate($data_v['date_from'], null, $fullDate);
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['data_to'] = Tools::displayDate($data_v['date_to'], null, $fullDate);
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['num_days'] = $num_days;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['adults'] = $data_v['adults'];
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['children'] = $data_v['children'];

                                $cart_htl_data[$type_key]['date_diff'][$date_join]['total_price_tax_excl'] = 0;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['total_price_tax_incl'] = 0;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_ti'] = 0;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_te'] = 0;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['avg_paid_unit_price_tax_excl'] = 0;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['amount'] = 0;
                                $cart_htl_data[$type_key]['date_diff'][$date_join]['id_room'] = $data_v['id_room'];
                            }

                            $cart_htl_data[$type_key]['date_diff'][$date_join]['extra_demands_price_te'] = $objBookingDemand->getRoomTypeBookingExtraDemands(
                                $order_obj->id,
                                $type_value['product_id'],
                                0,
                                $data_v['date_from'],
                                $data_v['date_to'],
                                0,
                                1,
                                0,
                                $data_v['id']
                            );
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['extra_demands_price_ti'] = $objBookingDemand->getRoomTypeBookingExtraDemands(
                                $order_obj->id,
                                $type_value['product_id'],
                                0,
                                $data_v['date_from'],
                                $data_v['date_to'],
                                0,
                                1,
                                1,
                                $data_v['id']
                            );

                            $totalDemandsPriceTE += $cart_htl_data[$type_key]['date_diff'][$date_join]['extra_demands_price_te'];
                            $totalDemandsPriceTI += $cart_htl_data[$type_key]['date_diff'][$date_join]['extra_demands_price_ti'];

                            $cart_htl_data[$type_key]['date_diff'][$date_join]['total_price_tax_excl'] += $data_v['total_price_tax_excl'];
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['total_price_tax_incl'] += $data_v['total_price_tax_incl'];
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_ti'] += $objServiceProductOrderDetail->getroomTypeServiceProducts(
                                $order_obj->id,
                                0,
                                0,
                                $data_v['id_product'],
                                $data_v['date_from'],
                                $data_v['date_to'],
                                $data_v['id_room'],
                                1,
                                1,
                                1,
                                Product::PRICE_ADDITION_TYPE_WITH_ROOM
                            );
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_te'] += $objServiceProductOrderDetail->getroomTypeServiceProducts(
                                $order_obj->id,
                                0,
                                0,
                                $data_v['id_product'],
                                $data_v['date_from'],
                                $data_v['date_to'],
                                $data_v['id_room'],
                                1,
                                0,
                                1,
                                Product::PRICE_ADDITION_TYPE_WITH_ROOM
                            );

                            if ($extraDemands = $objBookingDemand->getRoomTypeBookingExtraDemands(
                                $order_obj->id,
                                $type_value['product_id'],
                                $data_v['id_room'],
                                $data_v['date_from'],
                                $data_v['date_to'],
                                1,
                                0,
                                1,
                                $data_v['id']
                            )) {
                                $extraDemands = array_shift($extraDemands);
                                $extraDemands['product_id'] = $type_value['product_id'];
                                $extraDemands['room_type_name'] = $type_value['product_name'];
                                $extraDemands['date_from'] = $data_v['date_from'];
                                $extraDemands['date_to'] = $data_v['date_to'];
                                $room_extra_demands[] = $extraDemands;
                            }

                            if ($additionalServices = $objServiceProductOrderDetail->getRoomTypeServiceProducts(
                                0,
                                0,
                                0,
                                0,
                                0,
                                0,
                                0,
                                0,
                                1,
                                0,
                                null,
                                0,
                                $data_v['id']
                            )) {
                                $additionalServices['product_id'] = $type_value['product_id'];
                                $additionalServices['room_type_name'] = $type_value['product_name'];
                                $additionalServices['date_from'] = $data_v['date_from'];
                                $additionalServices['date_to'] = $data_v['date_to'];
                                $room_additinal_services[] = $additionalServices[$data_v['id']];
                            }

                            if ($taxes = OrderDetail::getTaxListStatic($data_v['id_order_detail'])) {
                                $tax_temp = array();
                                foreach ($taxes as $tax) {
                                    $obj = new Tax($tax['id_tax']);
                                    $tax_temp[] = sprintf($this->l('%1$s%2$s%%'), ($obj->rate + 0), '');
                                }
                                $cart_htl_data[$type_key]['order_detail_tax_label'] = implode(', ', $tax_temp);
                            } else {
                                $cart_htl_data[$type_key]['order_detail_tax_label'] = HTMLTemplateInvoice::l('No tax');
                            }
                        }

                        foreach ($cart_htl_data[$type_key]['date_diff'] as $date_join => $val) {
                            $num_days = $val['num_days'];
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['paid_unit_price_tax_excl'] = $val['total_price_tax_excl']/$num_days;
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['paid_unit_price_tax_incl'] = $val['total_price_tax_excl']/$num_days;
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['amount'] += ($val['total_price_tax_excl'] + $cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_te']);
                            $cart_htl_data[$type_key]['date_diff'][$date_join]['avg_paid_unit_price_tax_excl'] += $cart_htl_data[$type_key]['date_diff'][$date_join]['paid_unit_price_tax_excl'] + ($cart_htl_data[$type_key]['date_diff'][$date_join]['additional_services_price_auto_add_te']/$num_days);
                        }
                        foreach ($cart_htl_data[$type_key]['date_diff'] as $key => &$value) {
                            $value['avg_paid_unit_price_tax_excl'] = Tools::ps_round($value['avg_paid_unit_price_tax_excl'] / $value['num_rm'], 6);
                        }
                    } else if (Product::SELLING_PREFERENCE_HOTEL_STANDALONE == $type_value['selling_preference_type']
                        || Product::SELLING_PREFERENCE_STANDALONE == $type_value['selling_preference_type']
                    ) {
                        $serviceProducts = $objServiceProductOrderDetail->getServiceProductsInOrder($order_obj->id, $type_value['id_order_detail'], $type_value['product_id']);
                        if ($taxes = OrderDetail::getTaxListStatic($type_value['id_order_detail'])) {
                            $tax_temp = array();
                            foreach ($taxes as $tax) {
                                $obj = new Tax($tax['id_tax']);
                                $tax_temp[] = sprintf($this->l('%1$s%2$s%%'), ($obj->rate + 0), '');
                            }
                            $order_detail_tax_label = implode(', ', $tax_temp);
                        } else {
                            $order_detail_tax_label = HTMLTemplateInvoice::l('No tax');
                        }
                        foreach ($serviceProducts as $serviceProduct) {
                            if ($serviceProduct['id_hotel']) {
                                $objHotel = new HotelBranchInformation($serviceProduct['id_hotel'], $order_obj->id_lang);
                                $serviceProduct['hotel_name'] = $objHotel->hotel_name;
                            }
                            $serviceProduct = array_merge($type_value, $serviceProduct);
                            $serviceProduct['order_detail_tax_label'] = $order_detail_tax_label;
                            $service_product_data[] = $serviceProduct;
                        }
                    }
                }
                unset($tax_temp);

                $footer['total_extra_demands_ti'] = $totalDemandsPriceTI;
                $footer['total_extra_demands_te'] = $totalDemandsPriceTE;
            }
        }

        $footer['room_price_tax_excl'] = 0;
        $footer['room_price_tax_incl'] = 0;
        $footer['additional_service_price_tax_excl'] = 0;
        $footer['additional_service_price_tax_incl'] =0;
        $footer['total_convenience_fee_te'] = 0;
        $footer['total_convenience_fee_ti'] = 0;
        if ($idsOrderDetail = array_column($order_details, 'id_order_detail')) {
            $footer['room_price_tax_excl'] = $this->order->getTotalProductsWithoutTaxes(false, true, null, null, null, $idsOrderDetail) + $this->order->getTotalProductsWithoutTaxes(false, false, Product::SELLING_PREFERENCE_WITH_ROOM_TYPE, 1, Product::PRICE_ADDITION_TYPE_WITH_ROOM, $idsOrderDetail);
            $footer['room_price_tax_incl'] = $this->order->getTotalProductsWithTaxes(false, true, null, null, null, $idsOrderDetail) + $this->order->getTotalProductsWithTaxes(false, false, Product::SELLING_PREFERENCE_WITH_ROOM_TYPE, 1, Product::PRICE_ADDITION_TYPE_WITH_ROOM, $idsOrderDetail);
            $footer['service_products_price_tax_excl'] = $this->order->getTotalProductsWithoutTaxes(false, false, Product::SELLING_PREFERENCE_STANDALONE, false, false, $idsOrderDetail) + $this->order->getTotalProductsWithoutTaxes(false, false, Product::SELLING_PREFERENCE_HOTEL_STANDALONE, false, false, $idsOrderDetail);
            $footer['service_products_price_tax_incl'] = $this->order->getTotalProductsWithTaxes(false, false, Product::SELLING_PREFERENCE_STANDALONE, false, false, $idsOrderDetail) + $this->order->getTotalProductsWithTaxes(false, false, Product::SELLING_PREFERENCE_HOTEL_STANDALONE, false, false, $idsOrderDetail);
            $footer['additional_service_price_tax_excl'] = $this->order->getTotalProductsWithoutTaxes(false, false, Product::SELLING_PREFERENCE_WITH_ROOM_TYPE, 0, null, $idsOrderDetail) + $totalDemandsPriceTE;
            $footer['additional_service_price_tax_incl'] = $this->order->getTotalProductsWithTaxes(false, false, Product::SELLING_PREFERENCE_WITH_ROOM_TYPE, 0, null, $idsOrderDetail) + $totalDemandsPriceTI;
            $footer['total_convenience_fee_te'] = $this->order->getTotalProductsWithoutTaxes(false, false, Product::SELLING_PREFERENCE_WITH_ROOM_TYPE, 1, Product::PRICE_ADDITION_TYPE_INDEPENDENT, $idsOrderDetail);
            $footer['total_convenience_fee_ti'] = $this->order->getTotalProductsWithTaxes(false, false, Product::SELLING_PREFERENCE_WITH_ROOM_TYPE, 1, Product::PRICE_ADDITION_TYPE_INDEPENDENT, $idsOrderDetail);
        }

        $footer['total_paid_real'] = $this->order_invoice->getTotalPaid();
        $footer['total_without_discount_te'] = $footer['room_price_tax_excl'] + $footer['total_convenience_fee_te'] + $footer['additional_service_price_tax_excl'] + $footer['service_products_price_tax_excl'];
        $footer['total_without_discount_ti'] = $footer['room_price_tax_incl'] + $footer['total_convenience_fee_ti'] + $footer['additional_service_price_tax_incl'] + $footer['service_products_price_tax_incl'];

        $footer['total_tax_without_discount'] = $footer['total_without_discount_ti'] - $footer['total_without_discount_te'];
        if ($footer['total_tax_without_discount'] < 0) {
            $footer['total_tax_without_discount'] = 0;
        }

        $data = array(
            'cart_htl_data' => $cart_htl_data,
            'is_hotel_order' => HotelBookingDetail::getIdHotelByIdOrder($this->order->id),
            'service_product_data' => $service_product_data,
            'fee_products' => $fee_products,
            'room_extra_demands' => $room_extra_demands,
            'room_additinal_services' => $room_additinal_services,
            'order' => $this->order,
            'order_invoice' => $this->order_invoice,
            'order_details' => $order_details,
            'cart_rules' => $cart_rules,
            'delivery_address' => $formatted_delivery_address,
            'invoice_address' => $formatted_invoice_address,
            'hotel_address' => $formattedHotelAddress,
            'addresses' => array('invoice' => $invoice_address, 'delivery' => $delivery_address),
            'tax_excluded_display' => $tax_excluded_display,
            'display_product_images' => $display_product_images,
            'layout' => $layout,
            'customer' => $customer,
            'footer' => $footer,
            'ps_price_compute_precision' => _PS_PRICE_COMPUTE_PRECISION_,
            'round_type' => $round_type,
            'legal_free_text' => $legal_free_text
        );

        if (Tools::getValue('debug')) {
            die(json_encode($data));
        }

        $this->smarty->assign($data);

        $tpls = array(
            'style_tab' => $this->smarty->fetch($this->getTemplate('invoice.style-tab')),
            'addresses_tab' => $this->smarty->fetch($this->getTemplate('invoice.addresses-tab')),
            'summary_tab' => $this->smarty->fetch($this->getTemplate('invoice.summary-tab')),
            'product_tab' => $this->smarty->fetch($this->getTemplate('invoice.product-tab')),
            'service_product_tab' => $this->smarty->fetch($this->getTemplate('invoice.service-product-tab')),
            'extra_demands_tab' => $this->smarty->fetch($this->getTemplate('invoice.extra-demands-tab')),
            'tax_tab' => $this->getTaxTabContent(),
            'payment_tab' => $this->smarty->fetch($this->getTemplate('invoice.payment-tab')),
            'note_tab' => $this->smarty->fetch($this->getTemplate('invoice.note-tab')),
            'total_tab' => $this->smarty->fetch($this->getTemplate('invoice.total-tab')),
            'shipping_tab' => $this->smarty->fetch($this->getTemplate('invoice.shipping-tab')),
        );
        $this->smarty->assign($tpls);

        return $this->smarty->fetch($this->getTemplateByCountry($country->iso_code));
    }

    public function getTaxTabContent()
    {
        $debug = Tools::getValue('debug');

        $address = new Address((int)$this->order->id_address_tax);
        $carrier = new Carrier($this->order->id_carrier);

        $showTaxName = 0;

        if ($tax_breakdowns = $this->getTaxBreakdown()) {
            if (Configuration::get('PS_INVOICE_TAXES_BREAKDOWN')) {
                $showTaxName = 1;
                foreach ($tax_breakdowns as &$taxDetails) {
                    foreach ($taxDetails as &$tax) {
                        if (isset($tax['id_tax'])) {
                            if (Validate::isLoadedObject($objTax = new Tax($tax['id_tax']))) {
                                $tax['name'] = $objTax->name[$this->order->id_lang];
                            }
                        }
                    }
                }
            }
        }

        $data = array(
            'showTaxName' => $showTaxName,
            'use_one_after_another_method' => $this->order_invoice->useOneAfterAnotherTaxComputationMethod(),
            'display_tax_bases_in_breakdowns' => $this->order_invoice->displayTaxBasesInProductTaxesBreakdown(),
            'shipping_tax_breakdown' => $this->order_invoice->getShippingTaxesBreakdown($this->order),
            'ecotax_tax_breakdown' => $this->order_invoice->getEcoTaxTaxesBreakdown(),
            'wrapping_tax_breakdown' => $this->order_invoice->getWrappingTaxesBreakdown(),
            'tax_breakdowns' => $tax_breakdowns,
            'order' => $debug ? null : $this->order,
            'order_invoice' => $debug ? null : $this->order_invoice,
            'carrier' => $debug ? null : $carrier
        );

        if ($debug) {
            return $data;
        }

        $this->smarty->assign($data);

        return $this->smarty->fetch($this->getTemplate('invoice.tax-tab'));
    }
}