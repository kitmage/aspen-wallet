<?php

namespace FluentBookingPro\App\Services\Integrations\PaymentMethods\Offline;

class OfflineSettings
{
    public $settings;

    protected $methodHandler = 'fluent_booking_payment_settings_offline';

    public function __construct()
    {
        $settings = get_option($this->methodHandler, []);

        $settings = wp_parse_args($settings, static::getDefaults());

        $this->settings = $settings;
    }

    /**
     * @return array with default fields value
     */
    public static function getDefaults()
    {
        return [
            'is_active'     => 'no',
            'payment_label' => __('Offline Payment', 'fluent-booking-pro'),
        ];
    }

    public function get()
    {
        return $this->settings;
    }

    public function isActive()
    {
        return $this->settings['is_active'] == 'yes';
    }
}
