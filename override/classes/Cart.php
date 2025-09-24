<?php
class Cart extends CartCore
{
    public function getSummaryDetails($id_lang = null, $refresh = false)
    {
        $summary = parent::getSummaryDetails($id_lang, $refresh);

        if ($this->id && Module::isEnabled('hotelreservationsystem')) {
            $objCartBookingData = new HotelCartBookingData();
            if (method_exists($objCartBookingData, 'getRoomTypeTaxBreakdown')) {
                $room_type_tax_breakdown = $objCartBookingData->getRoomTypeTaxBreakdown($this->id);
                if ($room_type_tax_breakdown) {
                    if (isset($summary['tax_breakdown'])) {
                        $summary['tax_breakdown'] = array_merge($summary['tax_breakdown'], $room_type_tax_breakdown);
                    } else {
                        $summary['tax_breakdown'] = $room_type_tax_breakdown;
                    }

                    $total_tax = 0;
                    foreach ($room_type_tax_breakdown as $tax) {
                        $total_tax += $tax['amount'];
                    }
                    $summary['total_tax'] += $total_tax;
                }
            }
        }

        return $summary;
    }
}
