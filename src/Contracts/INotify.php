<?php

namespace Simplephp\PaymentSdk\Contracts;

interface INotify
{
    /**
     * 通知类型
     */
    const NOTIFY_PAY = 'pay';
    const NOTIFY_REFUND = 'refund';
    const NOTIFY_SIGN = 'sign';
    const NOTIFY_UNSIGN = 'unsign';
    const NOTIFY_SERVICE_MARKET_ORDER = 'service.market.order';
    const NOTIFY_OPEN_APP_AUTH = 'open.app.auth';
    const MCHTRANSFER_BATCH_FINISHED = 'mchtransfer.batch.finished';

    /**
     * @return string
     */
    public function getNotifyType(): string;

    /**
     * @param string $notifyType
     * @param array $notifyData
     * @return mixed
     */
    public function handle(string $serviceProvider, array $notifyData);
}