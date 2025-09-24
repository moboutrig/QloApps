<?php
class HotelCartBookingData extends HotelCartBookingDataCore
{
    public function getRoomTypeTaxBreakdown($id_cart)
    {
        if (!$this->context) {
            $this->context = Context::getContext();
        }

        $tax_breakdown = array();
        $cart_booking_data = $this->getCartCurrentDataByCartId($id_cart);

        if ($cart_booking_data) {
            foreach ($cart_booking_data as $booking) {
                $tax_breakdown = $this->getBookingTaxBreakdown($booking, $tax_breakdown);
            }
        }

        return $tax_breakdown;
    }

    public function getBookingTaxBreakdown($booking, $tax_breakdown = array())
    {
        if (!$this->context) {
            $this->context = Context::getContext();
        }

        $id_hotel = $booking['id_hotel'];
        $id_address = HotelBranchInformation::getAddress($id_hotel);
        $tax_manager = TaxManagerFactory::getManager(new Address($id_address['id_address']), Product::getIdTaxRulesGroupByIdProduct($booking['id_product'], $this->context));
        $tax_calculator = $tax_manager->getTaxCalculator();

        $taxes = Tax::getTaxes($this->context->language->id);

        foreach ($taxes as $tax) {
            $tax_calculator->addTax(new Tax($tax['id_tax']));
        }

        $product_taxes = $tax_calculator->getTaxesAmount($booking['total_price_tax_excl']);

        foreach ($product_taxes as $id_tax => $amount) {
            $tax = new Tax($id_tax, $this->context->language->id);

            if ($tax->type == Tax::TAX_TYPE_PER_PERSON_PER_NIGHT) {
                $num_nights = HotelHelper::getNumberOfDays($booking['date_from'], $booking['date_to']);
                $num_guests = $booking['adults'] + $booking['children'];
                $final_amount = $tax->amount * $num_guests * $num_nights;
            } else {
                $final_amount = $amount;
            }

            if (!isset($tax_breakdown[$id_tax])) {
                $tax_breakdown[$id_tax] = array(
                    'name' => $tax->name,
                    'rate' => $tax->rate,
                    'amount' => 0,
                    'type' => $tax->type,
                    'id_tax' => $tax->id,
                );
            }
            $tax_breakdown[$id_tax]['amount'] += $final_amount;
        }

        return $tax_breakdown;
    }
}
