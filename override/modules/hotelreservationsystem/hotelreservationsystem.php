<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'hotelreservationsystem/classes/WebserviceSpecificManagementHotelGeneralSettings.php';

class HotelReservationSystemOverride extends HotelReservationSystem
{
    public function hookAddWebserviceResources()
    {
        $resources = parent::hookAddWebserviceResources();

        $newResources = [
            'hotel_general_settings' => [
                'description' => 'Hotel General Settings',
                'specific_management' => true,
            ],
        ];

        return array_merge($resources, $newResources);
    }
}
