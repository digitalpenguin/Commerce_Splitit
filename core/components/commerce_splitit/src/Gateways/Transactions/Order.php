<?php

namespace DigitalPenguin\Commerce_Splitit\Gateways\Transactions;

use DigitalPenguin\Commerce_Splitit\API\Response;
use modmore\Commerce\Gateways\Interfaces\TransactionInterface;

class Order implements TransactionInterface
{
    protected $isPaid;
    protected $orderId;
    protected $orderData;
    protected $verifyData;

    public function __construct($order, $isPaid, $orderData, $verifyResponse = null)
    {
        $this->orderId = $order->get('id');
        $this->isPaid = $isPaid;
        $this->orderData = $orderData;
        if ($verifyResponse instanceof Response) {
            $this->verifyData = $verifyResponse->getData();
        }
    }

    /**
     * Indicate if the transaction was paid
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    /**
     * Indicate if a transaction is waiting for confirmation/cancellation/failure. This is the case when a payment
     * is handled off-site, offline, or asynchronously in another why.
     *
     * When a transaction is marked as awaiting confirmation, a special page is shown when the customer returns
     * to the checkout.
     *
     * If the payment is a redirect (@see WebhookTransactionInterface), the payment pending page will offer the
     * customer to return to the redirectUrl.
     *
     * @return bool
     */
    public function isAwaitingConfirmation(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * Indicate if the payment has failed.
     *
     * @return bool
     * @see TransactionInterface::getExtraInformation()
     */
    public function isFailed(): bool
    {
        return false;
    }

    /**
     * Indicate if the payment was cancelled by the user (or possibly merchant); which is a separate scenario
     * from a payment that failed.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return false;
    }

    /**
     * If an error happened, return the error message.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return '';
    }

    /**
     * Return the (payment providers') reference for this order. Treated as a string.
     *
     * @return string
     */
    public function getPaymentReference(): string
    {
        if (empty($this->orderData)) {
            return 'Reference number missing';
        }
        $planData = $this->orderData['splitit_data'];

        return $planData['ipn'];
    }

    /**
     * Return a key => value array of transaction information that should be made available to merchant users
     * in the dashboard.
     *
     * @return array
     */
    public function getExtraInformation(): array
    {
        if (empty($this->orderData)) {
            return [];
        }

        $planData = $this->orderData['splitit_data'];

        $extra = [];

        if (array_key_exists('ipn', $planData)) {
            $extra['splitit_installment_plan_number'] = $planData['ipn'];
        }

        return $extra;
    }

    /**
     * Return an array of all (raw) transaction data, for debugging purposes.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->orderData;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}