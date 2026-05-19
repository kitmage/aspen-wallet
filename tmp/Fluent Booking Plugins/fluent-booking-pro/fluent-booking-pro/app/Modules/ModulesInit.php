<?php

namespace FluentBookingPro\App\Modules;

use FluentBooking\Framework\Support\Arr;
use FluentBooking\App\Services\Helper;

class ModulesInit
{
    public function register($app)
    {
        $settings = Helper::getPrefSettins();

        if (Arr::get($settings, 'coupon.enabled') == 'yes') {
            (new \FluentBookingPro\App\Modules\Coupon\CouponModule())->register($app);
        }
    }
}
