<?php
class Cart extends CartCore
{
    public function getConvenienceFeeProductsDetails()
    {
        $convenienceFeeProducts = array();
        $products = $this->getProducts();
        if ($products) {
            $priceDisplay = Group::getPriceDisplayMethod(Group::getCurrent()->id);
            foreach ($products as $product) {
                if (isset($product['selling_preference_type'])
                    && $product['selling_preference_type'] == Product::SELLING_PREFERENCE_WITH_ROOM_TYPE
                    && isset($product['price_addition_type'])
                    && $product['price_addition_type'] == Product::PRICE_ADDITION_TYPE_INDEPENDENT
                ) {
                    if ($priceDisplay) {
                        $price = Tools::displayPrice($product['total']);
                    } else {
                        $price = Tools::displayPrice($product['total_wt']);
                    }
                    $convenienceFeeProducts[] = array(
                        'name' => $product['name'],
                        'price' => $price,
                    );
                }
            }
        }
        return $convenienceFeeProducts;
    }

    public function getSummaryDetails($id_lang = null, $refresh = false)
    {
        $summary = parent::getSummaryDetails($id_lang, $refresh);
        $summary['convenience_fee_details'] = $this->getConvenienceFeeProductsDetails();
        return $summary;
    }
}