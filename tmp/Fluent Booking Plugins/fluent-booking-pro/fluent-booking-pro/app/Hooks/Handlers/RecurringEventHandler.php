<?php

namespace FluentBookingPro\App\Hooks\Handlers;

use FluentBooking\App\Models\Booking;
use FluentBooking\Framework\Support\Arr;
use FluentBookingPro\App\Services\RecurringHelper;

class RecurringEventHandler
{
    public function register()
    {
        add_filter('fluent_booking/get_calendar_event_settings', [$this, 'getEventSettings'], 10, 1);
        add_filter('fluent_booking/booking_fields', [$this, 'maybeDisableAdditionalGuestFields'], 10, 2);
        add_filter('fluent_booking/schedule_validation_rules_data', [$this, 'addRecurringValidation'], 10, 3);
        add_filter('fluent_booking/initialize_booking_data', [$this, 'addRecurringData'], 10, 3);
        add_action('fluent_booking/booking_schedule_auto_cancelled', [$this, 'maybeCancelChildBookings'], 10, 1);
    }

    public function getEventSettings($settings)
    {
        if (!isset($settings['recurring_config'])) {
            $settings['recurring_config'] = [
                'enabled'        => false,
                'is_count_fixed' => false,
                'max_count'      => 2,
                'interval'       => 1,
                'frequency'      => 'weekly'
            ];
        }

        return $settings;
    }

    public function maybeDisableAdditionalGuestFields($bookingFields, $calendarEvent)
    {
        $isRecurring = $calendarEvent->isRecurringEvent();
        $isMultiGuest = $calendarEvent->isMultiGuestEvent();

        if ($isRecurring && $isMultiGuest) {
            foreach ($bookingFields as &$fields) {
                if (Arr::get($fields, 'name') == 'guests') {
                    $fields['enabled'] = false;
                    $fields['disable_alter'] = true;
                }
            }
        }

        return $bookingFields;
    }

    public function addRecurringValidation($validation, $postedData, $calendarEvent)
    {
        $recurringConfig = $calendarEvent->getRecurringConfig();
        if (!Arr::isTrue($recurringConfig, 'enabled')) {
            return $validation;
        }

        $maxCount = Arr::get($recurringConfig, 'max_count', 1);

        $validation['rules']['recurring_count'] = 'required|numeric|min:1|max:' . $maxCount;
        $validation['messages']['recurring_count.required'] = __('Recurring count field is required', 'fluent-booking-pro');
        $validation['messages']['recurring_count.min'] = __('Recurring count field must be at least 1', 'fluent-booking-pro');
        $validation['messages']['recurring_count.max'] = __('Recurring count field must be at most ', 'fluent-booking-pro') . $maxCount;

        return $validation;
    }

    public function addRecurringData($bookingData, $data, $calendarEvent)
    {
        $recurringConfig = $calendarEvent->getRecurringConfig();
        if (!Arr::isTrue($recurringConfig, 'enabled')) {
            return $bookingData;
        }

        $maxCount = $recurringConfig['max_count'];
        $isAdmin = Arr::get($data, 'source') == 'admin';
        $isCountFixed = Arr::isTrue($recurringConfig, 'is_count_fixed') && !$isAdmin;
        $recurringCount = Arr::get($data, 'recurring_count', 1);
        $recurringConfig['count'] = $isCountFixed ? $maxCount : min($recurringCount, $maxCount);

        if ($recurringConfig['count'] < 2) {
            return $bookingData;
        }

        $rruleString = RecurringHelper::generateRRuleString($recurringConfig);
        if (empty($rruleString)) {
            return $bookingData;
        }

        $occurrences = RecurringHelper::getOccurrences($rruleString, $bookingData);
        if (empty($occurrences)) {
            return $bookingData;
        }

        $bookingData['start_time'] = array_column($occurrences, 'start_time');
        $bookingData['end_time'] = array_column($occurrences, 'end_time');

        $bookingData['other_info'] = [
            'recurring_rrule' => $rruleString,
            'recurring_count' => $recurringConfig['count']
        ];

        return $bookingData;
    }

    public function maybeCancelChildBookings($booking)
    {
        if ($booking->parent_id) {
            return;
        }

        Booking::query()
            ->where('parent_id', $booking->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}