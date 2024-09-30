<?php

namespace Simplephp\PaymentSdk\Contracts;

interface IConfigAdapter
{
    public function getConfig(): array;
}