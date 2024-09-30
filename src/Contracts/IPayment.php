<?php

namespace Simplephp\PaymentSdk\Contracts;

interface IPayment
{
    public function pay(string $payMethod, array $params);

    public function query(array $order);

    public function cancel(array $order);

    public function close(array $order);

    public function refund(array $order);
}