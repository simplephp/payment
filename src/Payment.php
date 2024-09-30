<?php

namespace Simplephp\PaymentSdk;

use Simplephp\PaymentSdk\Provider\Alipay;
use Simplephp\PaymentSdk\Provider\Wechat;

/**
 * Class Payment
 * @package Simplephp\PaymentSdk
 * @method static Alipay alipay(string $merchant = 'default')
 * @method static Wechat wechat(string $merchant = 'default')
 */
class Payment
{
    /**
     * @var $config
     */
    public $config = [];

    /**
     * 支付方式
     * @var array
     */
    const SUPPORT = [
        'alipay',
        'wechat',
    ];

    /**
     * 支付宝APP支付
     */
    const ALIPAY_APP_PAY = 'ALIPAY_APP_PAY';

    /**
     * 支付宝APP支付签名方式
     */
    const ALIPAY_APP_PAY_SIGN = 'ALIPAY_APP_PAY_SIGN';

    /**
     * 支付宝wap支付
     */
    const ALIPAY_WAP_PAY = 'ALIPAY_WAP_PAY';
    /**
     * 支付宝web支付
     */
    const ALIPAY_WEB_PAY = 'ALIPAY_WEB_PAY';
    /**
     * 支付宝qr支付
     */
    const ALIPAY_QR_PAY = 'ALIPAY_QR_PAY';

    /**
     * 微信APP支付
     */
    const WECHAT_APP_PAY = 'WECHAT_APP_PAY';
    /**
     * 线下场所、公众号场景和PC网站场景
     */
    const WECHAT_JSAPI_PAY = 'WECHAT_JSAPI_PAY';
    /**
     * 微信wap支付
     */
    const WECCHAT_WAP_PAY = 'WECCHAT_WAP_PAY';
    /**
     * 微信小程序支付
     */
    const WECCHAT_QR_PAY = 'WECCHAT_QR_PAY';

    /**
     * @param array $config
     * @return string
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param array $config
     * @return Payment
     */
    public static function config(array $config): Payment
    {
        return new self($config);
    }

    /**
     * @param string $provider
     * @param array $config
     * @return mixed
     */
    public function __call(string $provider, array $arguments = [])
    {
        $provider = strtolower($provider);
        if (!in_array($provider, self::SUPPORT)) {
            throw new \InvalidArgumentException('不支持的支付方式');
        }
        $merchant = $arguments[0] ?? 'default';
        $config = $this->config[$provider][$merchant] ?? [];
        if (empty($config)) {
            throw new \InvalidArgumentException('支付方式配置信息不存在');
        }
        $class = __NAMESPACE__ . '\\Provider\\' . ucfirst($provider);
        return new $class($config);
    }
}