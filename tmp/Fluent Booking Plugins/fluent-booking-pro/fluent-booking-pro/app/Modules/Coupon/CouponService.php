<?php

namespace FluentBookingPro\App\Modules\Coupon;

use FluentBooking\Framework\Support\Arr;

class CouponService
{
    public static function getDefaultCouponData()
    {
        return apply_filters('fluent_booking/default_coupon_data', [
            'title'               => '',
            'coupon_code'         => '',
            'discount'            => 0,
            'discount_type'       => 'percentage',
            'min_purchase_amount' => 0,
            'max_discount_amount' => 0,
            'total_limit'         => 0,
            'per_user_limit'      => 0,
            'allowed_event_ids'   => [],
            'start_date'          => '',
            'end_date'            => '',
            'stackable'           => 'no',
            'status'              => 'active',
            'internal_note'       => '',
            'usage_count'         => 0,
            'failed_message'      => [
                'invalid'       => __('Sorry, the provided coupon is not valid', 'fluent-booking-pro'),
                'expired'       => __('Sorry, the provided coupon is expired', 'fluent-booking-pro'),
                'stackable'     => __('Sorry, you can not apply this coupon with other coupon code', 'fluent-booking-pro'),
                'min_purchase'  => __('Sorry, the provided coupon does not meet the requirements', 'fluent-booking-pro'),
                'limit_reached' => __('Sorry, the coupon has reached the limit', 'fluent-booking-pro')
            ]
        ]);
    }

    public static function createOrUpdateCouponSchema($data)
    {
        return [
            'object_id' => 1,
            'key'       => sanitize_text_field(Arr::get($data, 'coupon_code')),
            'value'     => [
                'title'               => sanitize_text_field(Arr::get($data, 'title')),
                'discount'            => round(Arr::get($data, 'discount'), 2),
                'discount_type'       => sanitize_text_field(Arr::get($data, 'discount_type')),
                'min_purchase_amount' => (int) Arr::get($data, 'min_purchase_amount', 0),
                'max_discount_amount' => (int) Arr::get($data, 'max_discount_amount', 0),
                'total_limit'         => (int) Arr::get($data, 'total_limit', 0),
                'per_user_limit'      => (int) Arr::get($data, 'per_user_limit', 0),
                'start_date'          => sanitize_text_field(Arr::get($data, 'start_date')),
                'end_date'            => sanitize_text_field(Arr::get($data, 'end_date')),
                'stackable'           => Arr::get($data, 'stackable') == 'yes' ? 'yes' : 'no',
                'status'              => Arr::get($data, 'status', 'active'),
                'internal_note'       => sanitize_text_field(Arr::get($data, 'internal_note')),
                'allowed_event_ids'   => array_map('intval', Arr::get($data, 'allowed_event_ids', [])),
                'usage_count'         => (int) Arr::get($data, 'usage_count', 0),
                'failed_message'      => array_map('sanitize_text_field', Arr::get($data, 'failed_message', []))
            ]
        ];
    }

    public static function formattedCoupon($coupon)
    {
        $couponValue = $coupon->value;

        $formattedCoupon = [
            'id'                  => $coupon->id,
            'coupon_code'         => $coupon->key,
            'title'               => Arr::get($couponValue, 'title', ''),
            'discount'            => Arr::get($couponValue, 'discount', ''),
            'discount_type'       => Arr::get($couponValue, 'discount_type', 'percentage'),
            'min_purchase_amount' => Arr::get($couponValue, 'min_purchase_amount', ''),
            'max_discount_amount' => Arr::get($couponValue, 'max_discount_amount', ''),
            'total_limit'         => Arr::get($couponValue, 'total_limit', ''),
            'per_user_limit'      => Arr::get($couponValue, 'per_user_limit', ''),
            'allowed_event_ids'   => Arr::get($couponValue, 'allowed_event_ids', []),
            'start_date'          => Arr::get($couponValue, 'start_date', ''),
            'end_date'            => Arr::get($couponValue, 'end_date', ''),
            'stackable'           => Arr::get($couponValue, 'stackable', 'no'),
            'usage_count'         => Arr::get($couponValue, 'usage_count', 0),
            'failed_message'      => Arr::get($couponValue, 'failed_message', []),
            'internal_note'       => Arr::get($couponValue, 'internal_note', ''),
            'status'              => Arr::get($couponValue, 'status', 'inactive')
        ];

        return $formattedCoupon;
    }

    public static function getCouponField($paymentField)
    {
        if ($coupon = Arr::get($paymentField, 'coupon', [])) {
            return $coupon;
        }

        return [
            'name'         => 'coupon_codes',
            'label'        => __('Have a Coupon?', 'fluent-booking-pro'),
            'placeholder'  => __('Apply Here', 'fluent-booking-pro'),
            'apply_button' => __('Apply', 'fluent-booking-pro')
        ];
    }

    public static function updateCouponFieldSaveData($field, $value, $calendarEvent)
    {
        if ($calendarEvent->type !== 'paid' || empty($value['coupon'])) {
            return $field;
        }

        $field['coupon'] = array_map('sanitize_text_field', $value['coupon']);

        return $field;
    }

    public static function validateCoupon($couponCode, $eventId, $totalAmount, $otherCoupons = [])
    {
        $coupon = CouponModel::getCouponByCode($couponCode);

        if (!$coupon) {
            return new \WP_Error('invalid_coupon', __('Invalid coupon code', 'fluent-booking-pro'));
        }

        if (in_array($couponCode, $otherCoupons)) {
            return new \WP_Error('invalid_coupon', __('This coupon code has already been applied. Please use a different coupon.', 'fluent-booking-pro'));
        }

        $couponData = static::formattedCoupon($coupon);

        $couponData['coupon'] = $coupon;
        $couponStatus = $couponData['status'];
        $failedMessages = $couponData['failed_message'];
        $invalidMessage = Arr::get($failedMessages, 'invalid');

        if (!empty($failedMessages[$couponStatus])) {
            return new \WP_Error('invalid_coupon', $failedMessages[$couponStatus]);
        }

        if ($couponStatus !== 'active') {
            return new \WP_Error('invalid_coupon', $invalidMessage);
        }

        if ($couponData['allowed_event_ids'] && !in_array($eventId, $couponData['allowed_event_ids'])) {
            return new \WP_Error('invalid_coupon', $invalidMessage);
        }

        if ($couponData['total_limit'] && $couponData['total_limit'] <= $couponData['usage_count']) {
            return new \WP_Error('invalid_coupon', Arr::get($failedMessages, 'limit_reached'));
        }

        if ($couponData['per_user_limit']) {
            $userId = get_current_user_id();
            if (!$userId) {
                return new \WP_Error('invalid_coupon', Arr::get($failedMessages, 'limit_reached'));
            }

            $usageCount = $coupon->couponUsedByUser($userId);

            if ($couponData['per_user_limit'] <= $usageCount) {
                return new \WP_Error('invalid_coupon', Arr::get($failedMessages, 'limit_reached'));
            }
        }

        if ($couponData['min_purchase_amount'] && $couponData['min_purchase_amount'] > (int) $totalAmount) {
            return new \WP_Error('invalid_coupon', Arr::get($failedMessages, 'min_purchase'));
        }

        if ($otherCoupons) {
            if ($couponData['stackable'] === 'no') {
                return new \WP_Error('invalid_coupon', Arr::get($failedMessages, 'stackable'));
            }

            foreach ($otherCoupons as $otherCoupon) {
                $otherCoupon = CouponModel::getCouponByCode($otherCoupon);
                if (!$otherCoupon) {
                    continue;
                }

                $otherCouponData = static::formattedCoupon($otherCoupon);
                if ($otherCouponData['stackable'] === 'no') {
                    return new \WP_Error('invalid_coupon', Arr::get($failedMessages, 'stackable'));
                }

                $discountAmount = static::getDiscountAmount($otherCouponData, $totalAmount);
                $totalAmount -= $discountAmount;
            }
        }

        $couponData['discount_amount'] = static::getDiscountAmount($couponData, $totalAmount);

        $couponData['total_amount'] = max(0, $totalAmount - $couponData['discount_amount']);

        return $couponData;
    }

    public static function getDiscountAmount($couponData, $totalAmount)
    {
        $discountAmount = $couponData['discount'];

        if ($couponData['discount_type'] == 'percentage') {
            $discountAmount = $totalAmount * $couponData['discount'] / 100;
        }

        if ($couponData['max_discount_amount'] &&  $couponData['max_discount_amount'] < $discountAmount) {
            $discountAmount = $couponData['max_discount_amount'];
        }

        return max(0, min($totalAmount, $discountAmount));
    }
}