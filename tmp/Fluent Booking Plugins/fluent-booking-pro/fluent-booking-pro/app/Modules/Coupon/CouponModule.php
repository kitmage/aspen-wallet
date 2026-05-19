<?php

namespace FluentBookingPro\App\Modules\Coupon;

use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Models\BookingMeta;
use FluentBooking\App\Services\Helper;
use FluentBooking\Framework\Support\Arr;

class CouponModule
{
    public function register($app)
    {
        /*
         * register the routes
         */
        $app->router->group(function ($router) {
            require_once __DIR__ . '/Http/coupon_api.php';
        });

        add_filter('fluent_booking/admin_vars', function ($vars) {
            $vars['default_coupon'] = CouponService::getDefaultCouponData();
            return $vars;
        }, 10, 1);

        add_filter('fluent_booking/payment_coupon_field', function ($couponField, $paymentField) {
            return CouponService::getCouponField($paymentField);
        }, 10, 2);

        add_filter('fluent_booking/save_event_booking_field_payment', function ($field, $value, $calendarEvent) {
            return CouponService::updateCouponFieldSaveData($field, $value, $calendarEvent);
        }, 10, 3);

        add_filter('fluent_booking/booking_data', [$this, 'addCouponInBookingData'], 10, 4);

        add_action('fluent_booking/after_booking_meta_update', [$this, 'addCouponInBookingMeta'], 10, 4);

        add_action('fluent_booking/after_order_items_created', [$this, 'createDiscountOrderItems'], 10, 4);

        add_action('fluent_booking/booking_schedule_auto_cancelled', [$this, 'maybeCancelCoupon'], 10, 1);

        add_action('wp_ajax_fluent_booking_apply_coupon', [$this, 'ajaxApplyCoupon']);
        add_action('wp_ajax_nopriv_fluent_booking_apply_coupon', [$this, 'ajaxApplyCoupon']);

        add_action('wp_ajax_fluent_booking_apply_bulk_coupons', [$this, 'ajaxApplyBulkCoupons']);
        add_action('wp_ajax_nopriv_fluent_booking_apply_bulk_coupons', [$this, 'ajaxApplyBulkCoupons']);
    }

    public function ajaxApplyCoupon()
    {
        $data = $_REQUEST;

        $eventId = (int) Arr::get($data, 'event_id');

        $calendarEvent = CalendarSlot::findOrFail($eventId);

        $couponCode = sanitize_text_field(Arr::get($data, 'coupon_code'));

        $otherCoupons = array_map('sanitize_text_field', Arr::get($data, 'other_coupons', []));

        $quantity = (int) Arr::get($data, 'quantity', 1) ?: 1;

        $duration = (int) Arr::get($data, 'duration', null);

        $totalAmount = $calendarEvent->getPricingTotal($duration) * $quantity;

        $couponData = CouponService::validateCoupon($couponCode, $eventId, $totalAmount, $otherCoupons);

        $couponData = apply_filters('fluent_booking/validate_coupon_data', $couponData, $couponCode, $eventId, $totalAmount, $otherCoupons);

        if (is_wp_error($couponData)) {
            wp_send_json([
                'message' => $couponData->get_error_message(),
            ], 422);
        }

        wp_send_json([
            'coupon' => $couponData
        ]);
    }

    public function ajaxApplyBulkCoupons()
    {
        $data = $_REQUEST;

        $couponCodes = array_map('sanitize_text_field', Arr::get($data, 'coupon_codes', []));

        $eventId = (int) Arr::get($data, 'event_id');

        $calendarEvent = CalendarSlot::findOrFail($eventId);

        $quantity = (int) Arr::get($data, 'quantity', 1) ?: 1;

        $duration = (int) Arr::get($data, 'duration', null);

        $totalAmount = $calendarEvent->getPricingTotal($duration) * $quantity;

        $appliedCoupons = [];
        foreach ($couponCodes as $code) {
            $couponData = CouponService::validateCoupon($code, $eventId, $totalAmount, array_keys($appliedCoupons));
            if (is_wp_error($couponData)) {
                continue;
            }

            $appliedCoupons[$code] = $couponData;
        }

        wp_send_json([
            'coupons' => array_values($appliedCoupons)
        ]);
    }

    public function addCouponInBookingData($bookingData, $calendarSlot, $customData, $postedData)
    {
        $couponCodes = (array) Arr::get($postedData, 'coupon_codes', []);

        if (!empty($couponCodes)) {
            $bookingData['coupon_codes'] = $couponCodes;
        }

        return $bookingData;
    }

    public function addCouponInBookingMeta($booking, $bookingData, $customFieldsData, $calendarEvent)
    {
        $couponCodes = (array) Arr::get($bookingData, 'coupon_codes', []);
        if (empty($couponCodes)) {
            return;
        }

        $quantity = (int) Arr::get($bookingData, 'quantity', 1) ?: 1;
        $duration = $booking->slot_minutes ?? null;
        $totalAmount = $calendarEvent->getPricingTotal($duration) * $quantity;
        $updatedAmount = $totalAmount;

        $appliedCoupons = [];
        foreach ($couponCodes as $code) {
            $couponData = CouponService::validateCoupon($code, $calendarEvent->id, $totalAmount, array_keys($appliedCoupons));

            $couponData = apply_filters('fluent_booking/validate_coupon_data', $couponData, $code, $calendarEvent->id, $totalAmount, array_keys($appliedCoupons));

            if (is_wp_error($couponData)) {
                continue;
            }

            $appliedCoupons[$code] = $couponData;
            $updatedAmount = $couponData['total_amount'];
            Helper::updateBookingMeta($booking->id, 'coupon', $code);

            if ($coupon = Arr::get($couponData, 'coupon')) {
                $coupon->value = ['usage_count' => ($couponData['usage_count'] ?? 0) + 1];
                $coupon->save();
            }
        }

        add_filter('fluent_booking/after_booking_data', function ($bookingData) use ($appliedCoupons, $updatedAmount) {
            $bookingData['applied_coupons'] = $appliedCoupons;
            $bookingData['total_amount'] = intval($updatedAmount * 100);
            return $bookingData;
        }, 10, 1);
    }

    public function createDiscountOrderItems($order, $booking, $calendarSlot, $bookingData)
    {
        $appliedCoupons = Arr::get($bookingData, 'applied_coupons', []);
        if (empty($appliedCoupons)) {
            return;
        }

        $orderItem = [];
        foreach ($appliedCoupons as $couponData) {
            $itemPrice = intval($couponData['discount_amount'] * 100);
            $orderItem['booking_id'] = $booking->id;
            $orderItem['item_name'] = $couponData['coupon_code'];
            $orderItem['item_price'] = $itemPrice;
            $orderItem['quantity'] = 1;
            $orderItem['item_total'] = $itemPrice;
            $orderItem['rate'] = 1;
            $orderItem['type'] = 'discount';
            $orderItem['line_meta'] = wp_json_encode($couponData);
            $order->items()->create($orderItem);
        }
    }

    public function maybeCancelCoupon($booking)
    {
        $couponCodes = BookingMeta::where('booking_id', $booking->id)
            ->where('meta_key', 'coupon')
            ->pluck('value');

        foreach ($couponCodes as $couponCode) {
            $coupon = CouponModel::getCouponByCode($couponCode);
            if ($coupon) {
                $coupon->value = ['usage_count' => max(0, ($coupon->value['usage_count'] ?? 0) - 1)];
                $coupon->save();
            }
        }
    }
}