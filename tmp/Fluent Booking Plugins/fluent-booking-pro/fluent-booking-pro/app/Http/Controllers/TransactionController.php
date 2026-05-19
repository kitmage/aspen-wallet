<?php

namespace FluentBookingPro\App\Http\Controllers;

use FluentBooking\App\Models\Booking;
use FluentBookingPro\App\Models\Transactions;
use FluentBooking\App\Http\Controllers\Controller;
use FluentBooking\Framework\Http\Request\Request;
use FluentBooking\Framework\Support\Arr;


class TransactionController extends Controller
{
    public function updateTransaction(Request $request, $id, $transactionId)
    {
        $booking = Booking::findOrFail($id);

        $transaction = Transactions::findOrFail($transactionId);

        $data = Arr::only($request->all(), ['vendor_charge_id', 'status', 'meta']);

        $oldStatus = $transaction->status;

        $transactionData = [
            'status' => sanitize_text_field(Arr::get($data, 'status')),
            'vendor_charge_id' => sanitize_text_field(Arr::get($data, 'vendor_charge_id')),
            'meta' => json_encode(Arr::get($data, 'meta', [])),
        ];

        $transaction->update($transactionData);

        if ($oldStatus !== $transactionData['status']) {
            $order = $booking->payment_order;

            do_action('fluent_booking/payment/status_changed', $order, $booking, $transactionData['status']);
            do_action('fluent_booking/payment/status_changed_' . $order->payment_method, $order, $booking, $transactionData);
            do_action('fluent_booking/log_booking_activity', $this->getPaymentStatusUpdateLog($booking->id, $transactionData['status']));
        }

        do_action('fluent_booking/transaction_updated', $transaction);

        return [
            'transaction' => $transaction,
            'message' => __('Transaction updated successfully', 'fluent-booking-pro')
        ];
    }

    private function getPaymentStatusUpdateLog($bookingId, $status)
    {
        $updatedBy = 'host';
        $status = ucfirst($status);

        $userId = get_current_user_id();
        if ($userId && $user = get_user_by('ID', $userId)) {
            $updatedBy = $user->display_name;
        }

        return [
            'booking_id'  => $bookingId,
            'status'      => 'closed',
            'type'        => 'success',
            'title'       => __('Payment Successfully Marked as', 'fluent-booking-pro') . ' ' . $status,
            'description' => __('Payment marked as', 'fluent-booking-pro') . ' ' . $status . ' ' . __('by', 'fluent-booking-pro') . ' ' . $updatedBy
        ];
    }
}
