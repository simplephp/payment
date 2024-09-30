<?php

namespace Simplephp\PaymentSdk\Abstracts;

use Simplephp\PaymentSdk\Contracts\IPayment;

abstract class APayment implements IPayment
{

    /**
     * 检查支付必要参数
     * @param array $params
     * @return array
     */
    protected function checkPayOptions(array $params): array
    {
        // $subject 订单标题 string(256)
        if (empty($params['subject'])) {
            throw new \InvalidArgumentException('缺省订单描述');
        }
        // $outTradeNo 64个字符以内，仅支持字母、数字、下划线
        if (!isset($params['trade_no']) || (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $params['trade_no']))) {
            throw new \InvalidArgumentException('商户订单号格式错误');
        }
        // $totalAmount 金额大于0
        if (!isset($params['amount']) || !is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new \InvalidArgumentException('订单金额为数字且大于0');
        }
        return [$params['subject'], $params['trade_no'], $params['amount']];
    }
}