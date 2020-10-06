<?php

namespace DigitalPenguin\Commerce_SplitIt\Gateways\Transactions;

use modmore\Commerce\Gateways\Interfaces\TransactionInterface;

class Order implements TransactionInterface
{
    private $reference;

    public function __construct($reference)
    {
        $this->reference = $reference;
    }

    public function isPaid()
    {
        return true;
    }

    public function isAwaitingConfirmation()
    {
        return false;
    }

    public function isRedirect()
    {
        return false;
    }

    public function isFailed()
    {
        return false;
    }

    public function isCancelled()
    {
        return false;
    }

    public function getErrorMessage()
    {
        return '';
    }

    public function getPaymentReference()
    {
        return $this->reference;
    }

    public function getExtraInformation()
    {
        return [];
    }

    public function getData()
    {
        return [];
    }
}