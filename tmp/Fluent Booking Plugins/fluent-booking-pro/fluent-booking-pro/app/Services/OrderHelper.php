<?php

namespace FluentBookingPro\App\Services;

use FluentBookingPro\App\Models\Order;
use FluentBookingPro\App\Models\Transactions;
use FluentBooking\App\Services\CurrenciesHelper;
use FluentBooking\Framework\Support\Arr;

class OrderHelper
{
    public $items = [];
    public $order;

    public function addItem($item)
    {
        $this->items[] = $item;
        return $this;
    }

    public function processDraftOrder($booking, $calendarSlot, $bookingData = [])
    {
        $items = $calendarSlot->getPaymentItems($booking->slot_minutes);

        $quantity = Arr::get($bookingData, 'quantity', 1);

        $subTotal = $this->getTotal($items) * $quantity;

        $total = Arr::get($bookingData, 'total_amount', $subTotal) ?? $subTotal;

        $currency = CurrenciesHelper::getGlobalCurrency();

        $orderData = [
            'status' => 'draft',
            'parent_id' => $booking->id,
            'order_number' => $booking->hash,
            'payment_method' => $booking->payment_method,
            'payment_method_title' => $booking->payment_method,
            'currency' => $currency,
            'subtotal' => $subTotal,
            'total_amount' => $total,
            'discount_total' => $subTotal - $total,
            'uuid' => $booking->hash
        ];

        $orderData = apply_filters('fluent_booking/create_draft_order', $orderData, $booking, $calendarSlot, $bookingData);

        $order = Order::query()->create($orderData);

        //create order Items
        $orderItem = [];
        foreach ($items as $item) {
            $itemPrice = intval($item['value'] * 100);
            $orderItem['booking_id'] = $booking->id;
            $orderItem['item_name'] = $item['title'];
            $orderItem['item_price'] = $itemPrice;
            $orderItem['quantity'] = $quantity;
            $orderItem['item_total'] = $itemPrice * $quantity;
            $orderItem['rate'] = 1;
            $orderItem['type'] = 'single';
            $orderItem['line_meta'] = wp_json_encode($item);
            $order->items()->create($orderItem);
        }

        do_action('fluent_booking/after_order_items_created', $order, $booking, $calendarSlot, $bookingData);

        $this->createDraftTransactions($order);

        return $order;
    }

    public function getTotal($items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += intval($item['value'] * 100);
        }
        return $total;
    }

    public function createDraftTransactions($order)
    {
        if (!$order) {
            return;
        }

        $transactionType = 'online';
        if ($order->payment_method == 'offline') {
            $transactionType = 'offline';
        }

        $transactionData = [
            'object_id' => $order->id,
            'object_type' => 'order',
            'transaction_type' => $transactionType,
            'payment_method' => $order->payment_method,
            'status' => 'pending',
            'total' => $order->total_amount,
            'rate' => 1,
            'uuid' => $order->uuid
        ];

        $transactionData = apply_filters('fluent_booking/create_draft_transactions', $transactionData, $order);

        Transactions::create($transactionData);
    }

    public function getOrderByHash($hash)
    {
        return Order::where('uuid', $hash)->first();
    }

    public function updateOrderStatus($orderHash, $status = 'pending')
    {
        $order = $this->getOrderByHash($orderHash);

        if (!$order) {
            return;
        }
        
        $order->status = $status;
        $order->save();
    }

}
