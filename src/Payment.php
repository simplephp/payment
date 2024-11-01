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