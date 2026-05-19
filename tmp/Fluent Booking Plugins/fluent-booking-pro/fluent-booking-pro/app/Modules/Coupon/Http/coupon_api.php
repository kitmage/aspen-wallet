<?php

/**
 * @var $router FluentBooking\Framework\Http\Router
 */

$router->prefix('coupons')->withPolicy(\FluentBooking\App\Http\Policies\SettingsPolicy::class)->group(function ($router) {
    $router->get('/', [\FluentBookingPro\App\Modules\Coupon\Http\Controllers\CouponController::class, 'getCoupons']);
    $router->post('/', [\FluentBookingPro\App\Modules\Coupon\Http\Controllers\CouponController::class, 'createCoupon']);
    $router->get('/{id}', [\FluentBookingPro\App\Modules\Coupon\Http\Controllers\CouponController::class, 'getCoupon']);
    $router->put('/{id}', [\FluentBookingPro\App\Modules\Coupon\Http\Controllers\CouponController::class, 'updateCoupon']);
    $router->delete('/{id}', [\FluentBookingPro\App\Modules\Coupon\Http\Controllers\CouponController::class, 'deleteCoupon']);
    $router->get('/event/{eventId}', [\FluentBookingPro\App\Modules\Coupon\Http\Controllers\CouponController::class, 'getCouponByCalendarEvent']);
});