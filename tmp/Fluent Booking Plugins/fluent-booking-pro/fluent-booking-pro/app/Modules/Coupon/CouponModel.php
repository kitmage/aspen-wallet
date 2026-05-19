<?php

namespace FluentBookingPro\App\Modules\Coupon;

use FluentBookingPro\App\Models\Model;
use FluentBooking\Framework\Support\Arr;
use FluentBooking\App\Models\Booking;
use FluentBooking\App\Models\BookingMeta;

class CouponModel extends Model
{
    protected $table = 'fcal_meta';

    protected $guarded = ['id'];

    protected $fillable = [
        'object_type', // coupon
        'object_id',   // active 1, otherwise 0
        'key',         // coupon_code
        'value'        // other settings
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->object_type = 'coupon';
        });

        static::updating(function ($model) {
            $model->object_type = 'coupon';
        });

        static::addGlobalScope('object_type', function ($query) {
            $query->where('object_type', 'coupon');
        });

        static::retrieved(function ($model) {
            $model->autoUpdateStatus();
        });
    }

    public function setValueAttribute($value)
    {
        $originalValue = $this->getOriginal('value');

        $originalValue = \maybe_unserialize($originalValue);

        foreach ($value as $key => $val) {
            $originalValue[$key] = $val;
        }

        $this->attributes['value'] = \maybe_serialize($originalValue);
    }

    public function getValueAttribute($value)
    {
        return \maybe_unserialize($value);
    }

    public static function getCouponByCode($couponCode)
    {
        return self::whereRaw('BINARY `key` = ?', [$couponCode])->first();
    }

    public function couponUsedByUser($userId)
    {
        $paidBookingIds = Booking::where('person_user_id', $userId)
            ->whereNotNull('payment_method')
            ->pluck('id');

        if (!$paidBookingIds) {
            return 0;
        }

        return BookingMeta::whereIn('booking_id', $paidBookingIds)
            ->where('meta_key', 'coupon')
            ->where('value', $this->key)
            ->count();
    }

    public function autoUpdateStatus()
    {
        $couponData = $this->value;

        $currentStatus = Arr::get($couponData, 'status', 'active');
        if (in_array($currentStatus, ['inactive', 'expired'])) {
            if ($this->object_id) {
                $this->object_id = 0;
                $this->save();
            }
            return;
        }

        $startDate = Arr::get($couponData, 'start_date', null);
        $endDate = Arr::get($couponData, 'end_date', null);

        $currentTime = current_datetime()->getTimestamp();

        $startDate = $startDate ? new \DateTime($startDate, wp_timezone()) : null;
        $endDate = $endDate ? new \DateTime($endDate, wp_timezone()) : null;

        if (!$startDate && !$endDate) {
            return;
        } else if ($startDate && $startDate->getTimestamp() > $currentTime) {
            $couponData['status'] = 'scheduled';
        } else if ($endDate && $endDate->getTimestamp() < $currentTime) {
            $couponData['status'] = 'expired';
        } else {
            $couponData['status'] = 'active';
        }

        $this->object_id = $couponData['status'] != 'active' ? 0 : 1;

        $this->value = $couponData;

        if ($currentStatus != $couponData['status']) {
            $this->save();
            do_action('fluent_booking/coupon_status_changed', $this, $currentStatus, $couponData['status']);
        }

        return;
    }
}