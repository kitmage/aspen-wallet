<?php

namespace FluentBookingPro\App\Modules\Coupon\Http\Controllers;

use FluentBooking\App\Services\CalendarService;
use FluentBooking\Framework\Http\Request\Request;
use FluentBooking\App\Http\Controllers\Controller;
use FluentBookingPro\App\Modules\Coupon\CouponModel;
use FluentBookingPro\App\Modules\Coupon\CouponService;
use FluentBooking\Framework\Support\Arr;

class CouponController extends Controller
{
    public function getCoupon(Request $request, $couponId)
    {
        $coupon = CouponModel::findOrFail($couponId);

        $data['coupon'] = CouponService::formattedCoupon($coupon);

        if (in_array('event_lists', $request->get('with', []))) {
            $data['event_lists'] = CalendarService::getCalendarOptionsByTitle();
        }

        return $data;
    }

    public function getCoupons(Request $request)
    {
        $data = [];

        $coupons = CouponModel::orderBy('id', 'desc')->paginate();

        $formattedCoupons = [];
        foreach ($coupons->items() as $coupon) {
            $formattedCoupons[] = CouponService::formattedCoupon($coupon);
        }
        
        $data['coupons'] = $formattedCoupons;

        $data['total_coupons'] = $coupons->total();

        if (in_array('event_lists', $request->get('with', []))) {
            $data['event_lists'] = CalendarService::getCalendarOptionsByTitle();
        }

        return $data;
    }

    public function createCoupon(Request $request)
    {
        $data = $request->get('coupon');

        $rules = [
            'coupon_code' => 'required|unique:fcal_meta,key',
            'title' => 'required',
            'discount' => 'required|numeric|min:1',
            'discount_type' => 'required|in:percentage,fixed',
            'min_purchase_amount' => 'numeric|min:0|nullable',
            'max_discount_amount' => 'numeric|min:0|nullable',
            'total_limit' => 'numeric|min:0|nullable',
            'per_user_limit' => 'numeric|min:0|nullable',
            'stackable' => 'in:yes,no',
            'status' => 'in:active,inactive,scheduled,expired'
        ];

        $messages = [
            'coupon_code.required' => __('Coupon code is required', 'fluent-booking-pro'),
            'coupon_code.unique' => __('Same coupon code already exists', 'fluent-booking-pro')
        ];

        $this->validate($data, $rules, $messages);

        $couponData = CouponService::createOrUpdateCouponSchema($data);

        $couponData = apply_filters('fluent_booking/create_coupon_data', $couponData);

        $coupon = CouponModel::create($couponData);

        do_action('fluent_booking/coupon_created', $coupon);

        return [
            'coupon'  => $coupon,
            'message' => __('Coupon created successfully', 'fluent-booking-pro'),
        ];
    }

    public function updateCoupon(Request $request, $couponId)
    {
        $data = $request->get('coupon');

        $rules = [
            'coupon_code' => 'required|unique:fcal_meta,key,' . $couponId . ',id',
            'title' => 'required',
            'discount' => 'required|numeric|min:1',
            'discount_type' => 'required|in:percentage,fixed',
            'min_purchase_amount' => 'numeric|min:0|nullable',
            'max_discount_amount' => 'numeric|min:0|nullable',
            'total_limit' => 'numeric|min:0|nullable',
            'per_user_limit' => 'numeric|min:0|nullable',
            'stackable' => 'in:yes,no',
            'status' => 'in:active,inactive,scheduled,expired'
        ];

        $messages = [
            'coupon_code.required' => __('Coupon code is required', 'fluent-booking-pro'),
            'coupon_code.unique' => __('Same coupon code already exists', 'fluent-booking-pro')
        ];

        $this->validate($data, $rules, $messages);

        $coupon = CouponModel::findOrFail($couponId);

        $couponData = CouponService::createOrUpdateCouponSchema($data);

        $couponData = apply_filters('fluent_booking/update_coupon_data', $couponData);

        $coupon->fill($couponData);

        $dirty = $coupon->getDirty();

        if ($dirty) {
            $coupon->save();
            do_action('fluent_booking/coupon_updated', $coupon);
        }

        return [
            'coupon' => $coupon,
            'message' => __('Coupon updated successfully', 'fluent-booking-pro'),
        ];
    }

    public function deleteCoupon(Request $request, $couponId)
    {
        $coupon = CouponModel::findOrFail($couponId);

        do_action('fluent_booking/before_delete_coupon', $coupon);

        $coupon->delete();

        do_action('fluent_booking/after_delete_coupon', $couponId);

        return [
            'message' => __('Coupon deleted successfully', 'fluent-booking-pro'),
        ];
    }

    public function getCouponByCalendarEvent(Request $request, $eventId)
    {
        $coupons = CouponModel::where('object_id', '!=', 0)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $eventId = (int) $eventId;
        $couponCodes = $coupons->filter(function ($coupon) use ($eventId) {
            $allowedEventIds = Arr::get($coupon->value, 'allowed_event_ids', []);
            return empty($allowedEventIds) || in_array($eventId, $allowedEventIds);
        })->pluck('key');

        return [
            'coupon_codes' => $couponCodes->toArray()
        ];
    }
}
