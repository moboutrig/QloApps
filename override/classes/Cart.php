<?php
class Cart extends CartCore
{
    public function getTaxesDetails()
    {
        $taxes_by_rate = [];

        if (Configuration::get('PS_TAX')) {
            $products = $this->getProducts();

            // Process taxes for products
            foreach ($products as $product) {
                // Do not include convenience fee products in tax breakdown, they will be displayed separately
                if (isset($product['price_addition_type']) && $product['price_addition_type'] == Product::PRICE_ADDITION_TYPE_INDEPENDENT) {
                    continue;
                }
                $address = new Address(Cart::getIdAddressForTaxCalculation($product['id_product']));
                $tax_manager = TaxManagerFactory::getManager(
                    $address,
                    Product::getIdTaxRulesGroupByIdProduct((int)$product['id_product'], Context::getContext())
                );
                $tax_calculator = $tax_manager->getTaxCalculator();
                $tax_amounts = $tax_calculator->getTaxesAmount($product['total']); // Use total price without tax

                foreach ($tax_amounts as $id_tax => $amount) {
                    $tax = new Tax($id_tax);
                    if (!Validate::isLoadedObject($tax)) {
                        continue;
                    }
                    $rate_key = (string)$tax->rate;
                    if (!isset($taxes_by_rate[$rate_key])) {
                        $taxes_by_rate[$rate_key] = [
                            'name' => $tax->name[Context::getContext()->language->id],
                            'rate' => $tax->rate,
                            'amount' => 0,
                        ];
                    }
                    $taxes_by_rate[$rate_key]['amount'] += $amount;
                }
            }

            // Process taxes for shipping
            if ($this->id_carrier) {
                $carrier = new Carrier($this->id_carrier);
                $shipping_address = new Address($this->id_address_delivery);
                if (Validate::isLoadedObject($carrier) && $carrier->active && Validate::isLoadedObject($shipping_address)) {
                    $tax_manager = TaxManagerFactory::getManager(
                        $shipping_address,
                        (int)$carrier->getIdTaxRulesGroup()
                    );
                    $tax_calculator = $tax_manager->getTaxCalculator();
                    $tax_amounts = $tax_calculator->getTaxesAmount($this->getOrderTotal(false, Cart::ONLY_SHIPPING)); // Use shipping cost without tax
                    foreach ($tax_amounts as $id_tax => $amount) {
                        $tax = new Tax($id_tax);
                        if (!Validate::isLoadedObject($tax)) {
                            continue;
                        }
                        $rate_key = (string)$tax->rate;
                        if (!isset($taxes_by_rate[$rate_key])) {
                            $taxes_by_rate[$rate_key] = [
                                'name' => $tax->name[Context::getContext()->language->id],
                                'rate' => $tax->rate,
                                'amount' => 0,
                            ];
                        }
                        $taxes_by_rate[$rate_key]['amount'] += $amount;
                    }
                }
            }

            // Process taxes for wrapping
            if ($this->gift) {
                $wrapping_address = new Address($this->id_address_delivery);
                $tax_manager = TaxManagerFactory::getManager(
                    $wrapping_address,
                    (int)Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP')
                );
                $tax_calculator = $tax_manager->getTaxCalculator();
                $tax_amounts = $tax_calculator->getTaxesAmount($this->getGiftWrappingPrice(false)); // Use wrapping price without tax
                foreach ($tax_amounts as $id_tax => $amount) {
                    $tax = new Tax($id_tax);
                    if (!Validate::isLoadedObject($tax)) {
                        continue;
                    }
                    $rate_key = (string)$tax->rate;
                    if (!isset($taxes_by_rate[$rate_key])) {
                        $taxes_by_rate[$rate_key] = [
                            'name' => $tax->name[Context::getContext()->language->id],
                            'rate' => $tax->rate,
                            'amount' => 0,
                        ];
                    }
                    $taxes_by_rate[$rate_key]['amount'] += $amount;
                }
            }
        }

        return $taxes_by_rate;
    }

    public function getSummaryDetails($id_lang = null, $refresh = false)
    {
        $summary = parent::getSummaryDetails($id_lang, $refresh);
        $summary['taxes_details'] = $this->getTaxesDetails();

        $summary['fee_products'] = [];
        $products_without_fees = [];

        if (isset($summary['products'])) {
            foreach ($summary['products'] as $product) {
                if (isset($product['price_addition_type']) && $product['price_addition_type'] == Product::PRICE_ADDITION_TYPE_INDEPENDENT) {
                    $summary['fee_products'][] = $product;
                } else {
                    $products_without_fees[] = $product;
                }
            }
        }

        $summary['products'] = $products_without_fees;

        // Adjust totals to exclude fee products
        $total_fee_wt = 0;
        $total_fee = 0;
        foreach ($summary['fee_products'] as $fee) {
            $total_fee_wt += $fee['total_wt'];
            $total_fee += $fee['total'];
        }

        $summary['total_products_wt'] -= $total_fee_wt;
        $summary['total_products'] -= $total_fee;

        // Recalculate total tax based on remaining products
        $summary['total_tax'] = $summary['total_products_wt'] - $summary['total_products'];

        // Adjust final price
        $summary['total_price'] -= $total_fee_wt;
        $summary['total_price_without_tax'] -= $total_fee;

        // Zero out the old convenience fee totals to avoid confusion
        $summary['convenience_fee_wt'] = 0;
        $summary['convenience_fee'] = 0;
        $summary['convenience_fee_tax'] = 0;

        return $summary;
    }
}