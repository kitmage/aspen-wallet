<?php

namespace FluentBookingPro\App\Http\Controllers;

use FluentBooking\App\Http\Controllers\Controller;
use FluentBooking\Framework\Http\Request\Request;
use FluentBooking\App\Services\Helper;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\Framework\Support\Arr;

class CalendarController extends Controller
{
    public function updateAssignments(Request $request, $calendarId, $eventId)
    {
        $data = $request->all();

        $this->validate($data,
            ['organizer_id' => 'numeric'],
            ['team_members' => 'required'],
            ['team_members.required' => __('There should be at least one member', 'fluent-booking-pro')]
        );

        $event = CalendarSlot::where('calendar_id', $calendarId)->findOrFail($eventId);

        if ($orgId = Arr::get($data, 'organizer_id')) {
            $event->user_id = $orgId;
        }

        $event->settings = [
            'team_members' => array_map('intval', Arr::get($data, 'team_members', []))
        ];

        $event->save();

        return [
            'message' => __('Data has been updated', 'fluent-booking-pro'),
            'event'   => $event
        ];
    }

    public function updateAdvancedSettings(Request $request, $calendarId, $eventId)
    {
        $data = $request->all();

        $event = CalendarSlot::where('calendar_id', $calendarId)->findOrFail($eventId);

        $rules = [
            'slug'                                  => 'required',
            'custom_redirect.redirect_url'          => 'required_if:custom_redirect.enabled,true',
            'custom_redirect.query_string'          => 'required_if:custom_redirect.is_query_string,yes',
            'multiple_booking.limit'                => 'required_if:multiple_booking.enabled,true',
            'can_not_cancel.type'                   => ['required_if:can_not_cancel.enabled,true','in:always,conditional'],
            'can_not_cancel.condition.unit'         => ['required_if:can_not_cancel.type,conditional','in:minutes,hours,days'],
            'can_not_reschedule.type'               => ['required_if:can_not_reschedule.enabled,true','in:always,conditional'],
            'can_not_reschedule.condition.unit'     => ['required_if:can_not_reschedule.type,conditional','in:minutes,hours,days'],
            'requires_confirmation.type'            => ['required_if:requires_confirmation.enabled,true','in:always,conditional'],
        ];

        $messages = [
            'slug.required'                                     => __('Event slug field is required', 'fluent-booking-pro'),
            'custom_redirect.redirect_url.required_if'          => __('Event redirect url field is required', 'fluent-booking-pro'),
            'custom_redirect.query_string.required_if'          => __('Event query string field is required', 'fluent-booking-pro'),
            'multiple_booking.limit.required_if'                => __('Event multiple booking limit field is required', 'fluent-booking-pro'),
            'requires_confirmation.type.required_if'            => __('Event confirmation type field is required', 'fluent-booking-pro'),
            'requires_confirmation.type.in'                     => __('Event confirmation type field is invalid', 'fluent-booking-pro')
        ];

        $validationConfig = apply_filters('fluent_booking/update_advanced_settings_validation_rule', [
            'rules'    => $rules,
            'messages' => $messages
        ], $event);

        $this->validate($data, $validationConfig['rules'], $validationConfig['messages']);

        if ($slug = sanitize_title(Arr::get($data, 'slug'))) {
            if (!Helper::isEventSlugAvailable($slug, true, $calendarId, $eventId)) {
                return $this->sendError([
                    'message' => __('The provided slug is not available. Please choose a different one', 'fluent-booking-pro')
                ], 422);
            }
            $event->slug = $slug;
        }

        $event->settings = [
            'booking_title'         => sanitize_text_field(Arr::get($data, 'booking_title')),
            'submit_button_text'    => sanitize_text_field(Arr::get($data, 'submit_button_text')),
            'custom_redirect'       => [
                'enabled'         => Arr::isTrue($data, 'custom_redirect.enabled'),
                'redirect_url'    => sanitize_url(Arr::get($data, 'custom_redirect.redirect_url')),
                'is_query_string' => Arr::get($data, 'custom_redirect.is_query_string') == 'yes' ? 'yes' : 'no',
                'query_string'    => sanitize_text_field(Arr::get($data, 'custom_redirect.query_string')),
            ],
            'requires_confirmation' => [
                'enabled'   => Arr::isTrue($data, 'requires_confirmation.enabled'),
                'type'      => sanitize_text_field(Arr::get($data, 'requires_confirmation.type')),
                'condition' => [
                    'unit'  => sanitize_text_field(Arr::get($data, 'requires_confirmation.condition.unit')),
                    'value' => intval(Arr::get($data, 'requires_confirmation.condition.value'))
                ]
            ],
            'multiple_booking'    => [
                'enabled'   => Arr::isTrue($data, 'multiple_booking.enabled'),
                'limit'     => intval(Arr::get($data, 'multiple_booking.limit'))
            ],
            'can_not_cancel'       => [
                'enabled'   => Arr::isTrue($data, 'can_not_cancel.enabled'),
                'type'      => sanitize_text_field(Arr::get($data, 'can_not_cancel.type')),
                'message'   => sanitize_text_field(Arr::get($data, 'can_not_cancel.message')),
                'condition' => [
                    'unit'  => sanitize_text_field(Arr::get($data, 'can_not_cancel.condition.unit')),
                    'value' => intval(Arr::get($data, 'can_not_cancel.condition.value'))
                ]
            ],
            'can_not_reschedule'   => [
                'enabled'   => Arr::isTrue($data, 'can_not_reschedule.enabled'),
                'type'      => sanitize_text_field(Arr::get($data, 'can_not_reschedule.type')),
                'message'   => sanitize_text_field(Arr::get($data, 'can_not_reschedule.message')),
                'condition' => [
                    'unit'  => sanitize_text_field(Arr::get($data, 'can_not_reschedule.condition.unit')),
                    'value' => intval(Arr::get($data, 'can_not_reschedule.condition.value'))
                ]
            ]
        ];

        $event->save();

        do_action('fluent_booking/after_update_advanced_settings', $event);

        return [
            'message' => __('Data has been updated', 'fluent-booking-pro'),
            'event'   => $event
        ];
    }

    public function updateRecurringSettings(Request $request, $calendarId, $eventId)
    {
        $data = $request->all();

        $event = CalendarSlot::where('calendar_id', $calendarId)->findOrFail($eventId);

        $rules = [
            'recurring_config.enabled'        => ['required','in:true,false'],
            'recurring_config.is_count_fixed' => ['required','in:true,false'],
            'recurring_config.max_count'      => ['required_if:recurring_config.enabled,true','numeric','min:0','max:24'],
            'recurring_config.interval'       => ['required_if:recurring_config.enabled,true','numeric','min:1','max:12'],
            'recurring_config.frequency'      => ['required_if:recurring_config.enabled,true','in:daily,weekly,monthly,yearly'],
        ];

        $messages = [
            'recurring_config.enabled.required'      => __('Recurring config enabled field is required', 'fluent-booking-pro'),
            'recurring_config.max_count.required_if' => __('Recurring config max count field is required', 'fluent-booking-pro'),
            'recurring_config.interval.required_if'  => __('Recurring config interval field is required', 'fluent-booking-pro'),
            'recurring_config.frequency.required_if' => __('Recurring config frequency field is required', 'fluent-booking-pro'),
            'recurring_config.frequency.in'          => __('Recurring config frequency field is invalid', 'fluent-booking-pro')
        ];

        $validationConfig = apply_filters('fluent_booking/update_recurring_settings_validation_rule', [
            'rules'    => $rules,
            'messages' => $messages
        ], $event);

        $this->validate($data, $validationConfig['rules'], $validationConfig['messages']);

        $isEnabled = Arr::isTrue($data, 'recurring_config.enabled');

        $event->settings = [
            'recurring_config' => [
                'enabled'        => $isEnabled,
                'is_count_fixed' => Arr::isTrue($data, 'recurring_config.is_count_fixed'),
                'max_count'      => intval(Arr::get($data, 'recurring_config.max_count')),
                'interval'       => intval(Arr::get($data, 'recurring_config.interval')),
                'frequency'      => sanitize_text_field(Arr::get($data, 'recurring_config.frequency')),
            ]
        ];

        if ($isEnabled && Arr::isTrue($event->settings, 'multiple_booking.enabled')) {
            $event->settings = ['multiple_booking' => ['enabled' => false, 'limit' => 1]];
        }

        $event->save();

        return [
            'message' => __('Data has been updated', 'fluent-booking-pro'),
            'event'   => $event
        ];
    }
}
