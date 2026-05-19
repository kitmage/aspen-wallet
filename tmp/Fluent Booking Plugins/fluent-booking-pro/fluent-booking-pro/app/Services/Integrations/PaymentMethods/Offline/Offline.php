<?php

namespace FluentBookingPro\App\Services\Integrations\PaymentMethods\Offline;

use FluentBookingPro\App\Services\Integrations\PaymentMethods\BasePaymentMethod;
use FluentBookingPro\App\Services\OrderHelper;
use FluentBookingPro\App\Models\Transactions;
use FluentBooking\Framework\Support\Arr;
use FluentBooking\App\Models\Booking;

class Offline extends BasePaymentMethod
{
    public $offlineSettings;
    
    public function __construct()
    {
        parent::__construct(
            __('Offline Payment', 'fluent-booking-pro'),
            'offline',
            '#136196',
            $this->getLogo()
        );

        $this->offlineSettings = $this->offlineSettings();
    }

    public function register()
    {
        $this->init();

        add_action('fluent_booking/payment/status_changed_offline', [$this, 'confirmOfflinePayment'], 10, 3);

        add_action('fluent_booking/payment/status_changed', [$this, 'updateStatus'], 10, 3);
    }

    public function isEnabled(): bool
    {
        return $this->getActiveStatus();
    }

    /**
     * @return string path of the svg image which will be used as checkout logo
     */
    public function getLogo()
    {
        return FLUENT_BOOKING_URL . "assets/images/payment-methods/offline.svg";
    }

    /**
     * @return string checkout methods short description
     * which will be shown to the checkout page and method settings page
     */
    public function getDescription()
    {
        return __("Pay with Offline", "fluent-booking-pro");
    }

    public function offlineSettings()
    {
        return new OfflineSettings();
    }

    public function getSettings()
    {
        return $this->offlineSettings->get();
    }

    public function makePayment($orderItem, $calendarEvent, $order)
    {
        (new OrderHelper())->updateOrderStatus($orderItem->hash, 'pending');

        $isRequireConf = $calendarEvent->isConfirmationRequired($orderItem->start_time, $orderItem->created_at);

        if (!$isRequireConf) {
            $orderItem->status = 'scheduled';
            $orderItem->save();

            $this->maybeUpdateChildBookings($orderItem, $calendarEvent);

            do_action('fluent_booking/pre_after_booking_scheduled', $orderItem, $calendarEvent, $orderItem);

            $booking = Booking::with(['calendar_event', 'calendar'])->find($orderItem->id);

            do_action('fluent_booking/after_booking_scheduled', $booking, $calendarEvent, $booking);
        }

        $successUrl = $this->getSuccessUrl($orderItem, $calendarEvent);

        wp_send_json_success([
            'nextAction'  => 'offline',
            'actionName'  => 'custom',
            'data'        => $orderItem,
            'redirect_to' => $successUrl,
            'status'      => 'success',
            'message'     => __('Order has been placed successfully', 'fluent-booking-pro'),
        ], 200);
    }

    public function refundPayment($orderItem, $calendarSlot)
    {
        return;
    }

    public function confirmOfflinePayment($order, $booking, $data = [])
    {        
        $transaction = Transactions::where('uuid', $order->uuid)->first();
        if (!$transaction) {
            return;
        }

        $paymentStatus = Arr::get($data, 'status', 'paid');

        if ($paymentStatus == 'refunded') {
            $this->processRefund($transaction, $order, $booking);
            return;
        }

        if ($paymentStatus != 'paid') {
            return;
        }

        if ($booking->getMeta('is_offline_action_fired') == 'yes') {
            return;
        }

        $paymentData = [
            'vendor_charge_id' => Arr::get($data, 'vendor_charge_id', $transaction->vendor_charge_id),
            'total_paid'       => Arr::get($data, 'total', $transaction->total),
            'meta'             => Arr::get($data, 'meta', json_encode($transaction->meta)),
            'status'           => $paymentStatus
        ];

        $booking->updateMeta('is_offline_action_fired', 'yes');

        $this->updateOrderData($order->uuid, $paymentData);
    }

    private function processRefund($transaction, $order, $booking)
    {
        if ($booking->payment_status == 'refunded') {
            return;
        }

        $refundAmount = $transaction->total * -100;

        $this->updateRefundData($refundAmount, $order, $transaction, $booking, 'offline', $transaction->vendor_charge_id, 'Refund From Offline');
    }

    private function maybeUpdateChildBookings($booking, $calendarEvent)
    {
        $childBookingIds = Booking::where('parent_id', $booking->id)
            ->pluck('id')
            ->toArray();

        if (!$childBookingIds) {
            return;
        }

        foreach ($childBookingIds as $bookingId) {
            $childBooking = Booking::find($bookingId);

            if (!$childBooking) {
                continue;
            }
        
            $childBooking->update([
                'status' => $booking->status,
            ]);

            do_action('fluent_booking/pre_after_booking_scheduled', $childBooking, $calendarEvent, $childBooking);

            $childBooking = Booking::with(['calendar_event', 'calendar'])->find($childBooking->id);

            do_action('fluent_booking/after_booking_scheduled', $childBooking, $calendarEvent, $childBooking);
        }
    }

    public function renderDescription()
    {
        echo '<p>' . esc_html__('Pay with Paypal', 'fluent-booking-pro') . '</p>';
    }

    public function fields()
    {
        return [
            'label'        => __('Offline Payments', 'fluent-booking-pro'),
            'description'  => __('Configure Offline to accept payments on your booking events and monetize your time slots', 'fluent-booking-pro'),
            'is_active'    => [
                'value' => 'no',
                'label' => __('Enable Offline payment for booking payment', 'fluent-booking-pro'),
                'type'  => 'inline_checkbox'
            ],
            'payment_label' => [
                'value' => '',
                'label' => __('Payment Label', 'fluent-booking-pro'),
                'type'  => 'text',
                'inline_help' => __('This label will be displayed in the booking form', 'fluent-booking-pro')
            ]
        ];
    }

    public function validSettingKeys()
    {
        return [
            'is_active',
            'payment_label'
        ];
    }

    public function webHookPaymentMethodName()
    {
        return $this->slug;
    }

    public function onPaymentEventTriggered()
    {
        return;
    }

    public function render($method)
    {
        do_action('fluent-booking/before_render_payment_method_' . $this->slug, $method);
        return '
            <input checked value="' . esc_attr($this->slug) . '" name="' . esc_attr($this->slug) . '_payment_method' . '" type="radio"  id="' . esc_attr($this->slug) . '_payment_method">
            <label for="' . esc_attr($this->slug) . '_payment_method">
              ' . __('Offline Payment', 'fluent-booking-pro') . '
            </label>
        ';
    }

    public function updateStatus($order, $booking, $status)
    {
        if (!$order || $order->payment_method != $this->slug) {
            return;
        }

        $order->status = $status;
        $order->save();

        $transaction = Transactions::where('uuid', $order->uuid)->first();
        if (!$transaction) {
            return;
        }

        $transaction->status = $status;
        $transaction->save();
    }
}
