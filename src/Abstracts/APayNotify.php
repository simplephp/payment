<?php

namespace Simplephp\PaymentSdk\Abstracts;

use Simplephp\PaymentSdk\Contracts\INotify;

abstract class APayNotify implements INotify
{
    public function getNotifyType(): string
    {
        return self::NOTIFY_PAY;
    }
}