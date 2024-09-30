<?php

namespace Simplephp\PaymentSdk\Abstracts;

use Simplephp\PaymentSdk\Contracts\INotify;

abstract class ARefundNotify implements INotify
{
    public function getNotifyType(): string
    {
        return self::NOTIFY_REFUND;
    }
}