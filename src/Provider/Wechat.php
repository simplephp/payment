<?php

namespace Simplephp\PaymentSdk\Provider;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\InflateStream;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Simplephp\PaymentSdk\Abstracts\APayment;
use Simplephp\PaymentSdk\Contracts\INotify;
use Simplephp\PaymentSdk\Models\Response;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Hash;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WeChatPay\Util\PemUtil;

/**
 * Class Wechat
 * @package Simplephp\PaymentSdk\Provider
 */
class Wechat extends APayment
{
    /**
     * 服务商名称
     */
    const SP_NAME = 'wechat';
    /**
     *  支付配置信息
     * @var $config
     */
    protected $config;

    /**
     * @var $instance BuilderChainable
     */
    protected $instance;


    /**
     * @var mixed|\OpenSSLAsymmetricKey|resource
     */
    private $merchantPrivateKeyInstance;

    /**
     *  公众号ID
     * @var mixed|string
     */
    private $appId;

    /**
     *  直连商户号
     * @var mixed|string
     */
    private $merchantId;
    /**
     * @var mixed|string
     */
    private $notifyUrl;

    /**
     * JSAPI 下单
     */
    const PREPARE_JSAPI = 'jsapi';

    /**
     * APP 下单
     */
    const PREPARE_APP = 'app';

    /**
     * H5 下单
     */
    const PREPARE_H5 = 'h5';

    /**
     * Native 下单
     */
    const PREPARE_NATIVE = 'native';

    /**
     * 币别
     */
    const DEFAULT_CURRENCY = 'CNY';

    /**
     * @var string
     */
    private $platformCertificateFilePath;

    /**
     * @var mixed|string
     */
    private $apiV3Key;

    /**
     * @var string
     */
    private $platformCertificateSerial;

    /**
     * @var mixed|\OpenSSLAsymmetricKey|resource
     */
    private $platformPublicKeyInstance;


    /**
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->initialization($config);
    }

    /**
     * 初始化
     * @param array $config
     * @throws \Exception
     */
    public function initialization(array $config)
    {
        // appid
        $appId = $config['app_id'] ?? '';
        if (empty($appId)) {
            throw new \InvalidArgumentException('微信商户app_id不能为空');
        }
        $this->appId = $appId;
        $merchantId = $config['mch_id'] ?? '';
        if (empty($merchantId)) {
            throw new \InvalidArgumentException('微信商户号不能为空');
        }
        $notifyUrl = $config['notify_url'] ?? '';
        if (!empty($notifyUrl)) {
            if (!filter_var($notifyUrl, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('微信异步通知地址不合法');
            }
            $this->notifyUrl = $notifyUrl;
        }
        $this->merchantId = $merchantId;
        $apiV3Key = $config['api_v3_key'] ?? '';
        if (empty($apiV3Key)) {
            throw new \InvalidArgumentException('微信商户APIv3密钥不能为空');
        }
        $this->apiV3Key = $apiV3Key;
        $merchantPrivateKeyFilePath = $config['merchant_private_key_file_path'] ?? '';
        if (empty($merchantPrivateKeyFilePath)) {
            throw new \InvalidArgumentException('微信商户私钥文件路径不能为空');
        }
        $merchantPrivateKeyFilePath = 'file://' . $merchantPrivateKeyFilePath;
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
        $this->merchantPrivateKeyInstance = $merchantPrivateKeyInstance;

        // 「商户API证书」的「证书序列号」
        $merchantCertificateSerial = $config['merchant_certificate_serial'] ?? '';
        if (empty($merchantCertificateSerial)) {
            throw new \InvalidArgumentException('微信商户API证书序列号不能为空');
        }
        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath = $config['platform_certificate_file_path'] ?? '';
        if (empty($platformCertificateFilePath)) {
            throw new \InvalidArgumentException('微信支付平台证书路径不能为空');
        }
        $platformCertificateFilePath = 'file://' . $config['platform_certificate_file_path'];
        $this->platformCertificateFilePath = $platformCertificateFilePath;

        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        $this->platformPublicKeyInstance = $platformPublicKeyInstance;
        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
        $this->platformCertificateSerial = $platformCertificateSerial;

        // 构造一个 APIv3 客户端实例
        $this->instance = Builder::factory([
            'mchid' => $merchantId,
            'serial' => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs' => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
    }

    /**
     * @param string $payMethod app支付|wap支付|web支付|qr扫码支付
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public function pay(string $payMethod, array $params)
    {
        switch ($payMethod) {
            case 'WECHAT_APP_PAY':
                return $this->appPay($params);
            case 'WECHAT_JSAPI_PAY':
                return $this->appJsApiPay($params);
            case 'WECCHAT_WAP_PAY':
                return $this->wapPay($params);
            case 'WECCHAT_QR_PAY':
                return $this->qrPay($params);
            default:
                throw new \Exception('支付方式不存在');
        }
    }


    /**
     * 微信-JSAPI支付适用于线下场所、公众号场景和PC网站场景
     * @link https://pay.weixin.qq.com/docs/merchant/apis/jsapi-payment/direct-jsons/jsapi-prepay.html
     * @param array $params
     * @throws \Exception
     */
    public function appJsApiPay(array $params)
    {
        try {
            [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
            // notify_url 选填 string(255) 异步接收微信支付结果通知的回调地址，通知URL必须为外网可访问的URL，不能携带参数。 公网域名必须为HTTPS，如果是走专线接入，使用专线NAT IP或者私有回调域名可使用HTTP
            $customNotifyUrl = $params['notify_url'] ?? '';
            if (!empty($customNotifyUrl)) {
                if (!filter_var($customNotifyUrl, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException('微信异步通知地址不合法');
                }
                $this->notifyUrl = $customNotifyUrl;
            }
            // currency 选填 string(16) 【货币类型】 CNY：人民币，境内商户号仅支持人民币。
            $currency = self::DEFAULT_CURRENCY;
            if (isset($params['currency']) && !empty($params['currency'])) {
                $currency = $params['currency'];
            }
            $businessParams = [
                'appid' => $this->appId,
                'mchid' => $this->merchantId,
                'description' => $subject,
                'out_trade_no' => $outTradeNo,
                'notify_url' => $this->notifyUrl,
                'amount' => [
                    'total' => $totalAmount * 100,
                    'currency' => $currency
                ],
            ];
            // time_expire 选填 string(64) 订单失效时间，遵循rfc3339标准格式，格式为yyyy-MM-DDTHH:mm:ss+TIMEZONE，yyyy-MM-DD表示年月日，T出现在字符串中，表示time元素的开头，HH:mm:ss表示时分秒，TIMEZONE表示时区（+08:00表示东八区时间，领先UTC8小时，即北京时间）。例如：2015-05-20T13:29:35+08:00表示，北京时间2015年5月20日13点29分35秒。
            if (isset($params['time_expire']) && !empty($params['time_expire'])) {
                $businessParams['time_expire'] = $params['time_expire'];
            }
            // attach 选填 string(127) 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。
            if (isset($params['attach']) && !empty($params['attach'])) {
                $businessParams['attach'] = $params['attach'];
            }
            // goods_tag 选填 string(32) 商品标记，代金券或立减优惠功能的参数。
            if (isset($params['goods_tag']) && !empty($params['goods_tag'])) {
                $businessParams['goods_tag'] = $params['goods_tag'];
            }
            // support_fapiao 选填 boolean 【电子发票入口开放标识】 传入true时，支付成功消息和支付详情页将出现开票入口。需要在微信支付商户平台或微信公众平台开通电子发票功能，传此字段才可生效。
            if (isset($params['support_fapiao']) && is_bool($params['support_fapiao'])) {
                $businessParams['support_fapiao'] = $params['support_fapiao'];
            }
            // payer 必填【支付者】 支付者信息
            if (!isset($params['payer']) || empty($params['payer'])) {
                throw new \InvalidArgumentException('支付者信息不能为空');
            } else {
                // openid 必填 string(128) 【用户标识】 用户在服务商 appid 下的唯一标识。
                if (!isset($params['payer']['openid']) || empty($params['payer']['openid'])) {
                    throw new \InvalidArgumentException('用户标识不能为空');
                }
                $businessParams['payer'] = $params['payer'];
            }

            // detail 选填 OrderDetail 优惠功能
            if (isset($params['detail']) && !empty($params['detail'])) {
                $businessParams['detail'] = $params['detail'];
            }
            // scene_info 选填 CommReqSceneInfo  场景信息
            if (isset($params['scene_info']) && !empty($params['scene_info'])) {
                $businessParams['scene_info'] = $params['scene_info'];
            }
            // settle_info 选填 SettleInfo  结算信息
            if (isset($params['settle_info']) && !empty($params['settle_info'])) {
                $businessParams['settle_info'] = $params['settle_info'];
            }
            $response = $this->instance
                ->chain('v3/pay/transactions/jsapi')
                ->post(['json' => $businessParams]);
            $result = json_decode($response->getBody(), true);
            if (!isset($result['prepay_id']) || empty($result['prepay_id'])) {
                throw new \Exception('微信预支付生成订单失败');
            }
            $prepayId = $result['prepay_id'];
            // @link https://pay.weixin.qq.com/docs/merchant/apis/jsapi-payment/jsapi-transfer-payment.html
            $params = [
                'appId' => $this->appId,
                'timeStamp' => (string)Formatter::timestamp(),
                'nonceStr' => Formatter::nonce(),
                'package' => 'prepay_id=' . $prepayId,
            ];
            $params += ['paySign' => Rsa::sign(
                Formatter::joinedByLineFeed(...array_values($params)),
                $this->merchantPrivateKeyInstance
            ), 'signType' => 'RSA'];
            return $this->response($params);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }

    /**
     * 微信-APP支付
     * @link https://pay.weixin.qq.com/docs/merchant/apis/in-app-payment/direct-jsons/app-prepay.html
     * @link https://pay.weixin.qq.com/docs/merchant/apis/in-app-payment/app-transfer-payment.html
     * @param array $params
     * @throws \Exception
     */
    public function appPay(array $params)
    {
        try {
            [$subject, $outTradeNo, $totalAmount, $notifyUrl] = $this->checkPayOptions($params);
            $businessParams = [
                'appid' => $this->appId,
                'mchid' => $this->merchantId,
                'description' => $subject,
                'out_trade_no' => $outTradeNo,
                'notify_url' => $notifyUrl,
                'amount' => [
                    'total' => $totalAmount * 100,
                    'currency' => self::DEFAULT_CURRENCY
                ],
            ];
            // time_expire 选填 string(64) 订单失效时间，遵循rfc3339标准格式，格式为yyyy-MM-DDTHH:mm:ss+TIMEZONE，yyyy-MM-DD表示年月日，T出现在字符串中，表示time元素的开头，HH:mm:ss表示时分秒，TIMEZONE表示时区（+08:00表示东八区时间，领先UTC8小时，即北京时间）。例如：2015-05-20T13:29:35+08:00表示，北京时间2015年5月20日13点29分35秒。
            if (isset($params['time_expire']) && !empty($params['time_expire'])) {
                $businessParams['time_expire'] = $params['time_expire'];
            }
            // attach 选填 string(127) 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。
            if (isset($params['attach']) && !empty($params['attach'])) {
                $businessParams['attach'] = $params['attach'];
            }
            // goods_tag 选填 string(32) 商品标记，代金券或立减优惠功能的参数。
            if (isset($params['goods_tag']) && !empty($params['goods_tag'])) {
                $businessParams['goods_tag'] = $params['goods_tag'];
            }
            // support_fapiao 选填 boolean 【电子发票入口开放标识】 传入true时，支付成功消息和支付详情页将出现开票入口。需要在微信支付商户平台或微信公众平台开通电子发票功能，传此字段才可生效。
            if (isset($params['support_fapiao']) && is_bool($params['support_fapiao'])) {
                $businessParams['support_fapiao'] = $params['support_fapiao'];
            }
            // amount 必填 CommReqAmountInfo 订单金额信息
            if (isset($params['currency']) && !empty($params['currency'])) {
                $businessParams['amount']['currency'] = $params['amount']['currency'];
            }
            // detail 选填 OrderDetail 优惠功能
            if (isset($params['detail']) && !empty($params['detail'])) {
                $businessParams['detail'] = $params['detail'];
            }
            // scene_info 选填 CommReqSceneInfo  场景信息
            if (isset($params['scene_info']) && !empty($params['scene_info'])) {
                $businessParams['scene_info'] = $params['scene_info'];
            }
            // settle_info 选填 SettleInfo  结算信息
            if (isset($params['settle_info']) && !empty($params['settle_info'])) {
                $businessParams['settle_info'] = $params['settle_info'];
            }
            $response = $this->instance
                ->chain('v3/pay/transactions/app')
                ->post(['json' => $businessParams]);
            $result = json_decode($response->getBody(), true);
            if (empty($result['prepay_id'])) {
                throw new \Exception('微信预支付生成订单失败');
            }
            $params = [
                'appId' => $this->appId,
                'partnerId' => $this->merchantId,
                'prepayId' => $result['prepay_id'],
                'package' => 'Sign=WXPay',
                'nonceStr' => Formatter::nonce(),
                'timeStamp' => (string)Formatter::timestamp(),
            ];
            $params += ['paySign' => Rsa::sign(
                Formatter::joinedByLineFeed(...array_values($params)),
                $this->merchantPrivateKeyInstance
            ), 'signType' => 'RSA'];
            return $this->response($params);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }


    /**
     * 微信-H5支付是指商户在微信客户端外的移动端网页展示商品或服务，用户在前述页面确认使用微信支付时，商户发起本服务呼起微信客户端进行支付。
     * 说明： 要求商户已有H5商城网站，并且已经过ICP备案，即可申请接入。
     * @link https://pay.weixin.qq.com/docs/merchant/products/h5-payment/introduction.html
     * @link https://pay.weixin.qq.com/docs/merchant/apis/h5-payment/h5-transfer-payment.html
     * @param array $params
     * @throws \Exception
     */
    public function wapPay(array $params)
    {
        try {
            [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
            // notify_url 选填 string(255) 异步接收微信支付结果通知的回调地址，通知URL必须为外网可访问的URL，不能携带参数。 公网域名必须为HTTPS，如果是走专线接入，使用专线NAT IP或者私有回调域名可使用HTTP
            $customNotifyUrl = $params['notify_url'] ?? '';
            if (!empty($customNotifyUrl)) {
                if (!filter_var($customNotifyUrl, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException('微信异步通知地址不合法');
                }
                $this->notifyUrl = $customNotifyUrl;
            }
            // currency 选填 string(16) 【货币类型】 CNY：人民币，境内商户号仅支持人民币。
            $currency = self::DEFAULT_CURRENCY;
            if (isset($params['currency']) && !empty($params['currency'])) {
                $currency = $params['currency'];
            }
            $businessParams = [
                'appid' => $this->appId,
                'mchid' => $this->merchantId,
                'description' => $subject,
                'out_trade_no' => $outTradeNo,
                'notify_url' => $this->notifyUrl,
                'amount' => [
                    'total' => $totalAmount * 100,
                    'currency' => $currency
                ],
            ];
            // time_expire 选填 string(64) 订单失效时间，遵循rfc3339标准格式，格式为yyyy-MM-DDTHH:mm:ss+TIMEZONE，yyyy-MM-DD表示年月日，T出现在字符串中，表示time元素的开头，HH:mm:ss表示时分秒，TIMEZONE表示时区（+08:00表示东八区时间，领先UTC8小时，即北京时间）。例如：2015-05-20T13:29:35+08:00表示，北京时间2015年5月20日13点29分35秒。
            if (isset($params['time_expire']) && !empty($params['time_expire'])) {
                $businessParams['time_expire'] = $params['time_expire'];
            }
            // attach 选填 string(127) 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。
            if (isset($params['attach']) && !empty($params['attach'])) {
                $businessParams['attach'] = $params['attach'];
            }
            // goods_tag 选填 string(32) 商品标记，代金券或立减优惠功能的参数。
            if (isset($params['goods_tag']) && !empty($params['goods_tag'])) {
                $businessParams['goods_tag'] = $params['goods_tag'];
            }
            // support_fapiao 选填 boolean 【电子发票入口开放标识】 传入true时，支付成功消息和支付详情页将出现开票入口。需要在微信支付商户平台或微信公众平台开通电子发票功能，传此字段才可生效。
            if (isset($params['support_fapiao']) && is_bool($params['support_fapiao'])) {
                $businessParams['support_fapiao'] = $params['support_fapiao'];
            }
            // detail 选填 OrderDetail 优惠功能
            if (isset($params['detail']) && !empty($params['detail'])) {
                $businessParams['detail'] = $params['detail'];
            }
            // scene_info 选填 CommReqSceneInfo  场景信息
            if (isset($params['scene_info']) && !empty($params['scene_info'])) {
                $businessParams['scene_info'] = $params['scene_info'];
            }
            // settle_info 选填 SettleInfo  结算信息
            if (isset($params['settle_info']) && !empty($params['settle_info'])) {
                $businessParams['settle_info'] = $params['settle_info'];
            }
            $response = $this->instance
                ->chain('v3/pay/transactions/h5')
                ->post(['json' => $businessParams]);
            $result = json_decode($response->getBody(), true);
            return $this->response($result);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }

    /**
     * 支付宝-QR支付,生成交易付款码，待用户扫码付款
     * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/direct-jsons/native-prepay.html
     * @param $params
     * @return Response
     * @throws \Exception
     */
    public function qrPay($params)
    {
        try {
            [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
            // notify_url 选填 string(255) 异步接收微信支付结果通知的回调地址，通知URL必须为外网可访问的URL，不能携带参数。 公网域名必须为HTTPS，如果是走专线接入，使用专线NAT IP或者私有回调域名可使用HTTP
            $customNotifyUrl = $params['notify_url'] ?? '';
            if (!empty($customNotifyUrl)) {
                if (!filter_var($customNotifyUrl, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException('微信异步通知地址不合法');
                }
                $this->notifyUrl = $customNotifyUrl;
            }
            // currency 选填 string(16) 【货币类型】 CNY：人民币，境内商户号仅支持人民币。
            $currency = self::DEFAULT_CURRENCY;
            if (isset($params['currency']) && !empty($params['currency'])) {
                $currency = $params['currency'];
            }
            $businessParams = [
                'appid' => $this->appId,
                'mchid' => $this->merchantId,
                'description' => $subject,
                'out_trade_no' => $outTradeNo,
                'notify_url' => $this->notifyUrl,
                'amount' => [
                    'total' => $totalAmount * 100,
                    'currency' => $currency
                ],
            ];
            // time_expire 选填 string(64) 订单失效时间，遵循rfc3339标准格式，格式为yyyy-MM-DDTHH:mm:ss+TIMEZONE，yyyy-MM-DD表示年月日，T出现在字符串中，表示time元素的开头，HH:mm:ss表示时分秒，TIMEZONE表示时区（+08:00表示东八区时间，领先UTC8小时，即北京时间）。例如：2015-05-20T13:29:35+08:00表示，北京时间2015年5月20日13点29分35秒。
            if (isset($params['time_expire']) && !empty($params['time_expire'])) {
                $businessParams['time_expire'] = $params['time_expire'];
            }
            // attach 选填 string(127) 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。
            if (isset($params['attach']) && !empty($params['attach'])) {
                $businessParams['attach'] = $params['attach'];
            }
            // goods_tag 选填 string(32) 商品标记，代金券或立减优惠功能的参数。
            if (isset($params['goods_tag']) && !empty($params['goods_tag'])) {
                $businessParams['goods_tag'] = $params['goods_tag'];
            }
            // support_fapiao 选填 boolean 【电子发票入口开放标识】 传入true时，支付成功消息和支付详情页将出现开票入口。需要在微信支付商户平台或微信公众平台开通电子发票功能，传此字段才可生效。
            if (isset($params['support_fapiao']) && is_bool($params['support_fapiao'])) {
                $businessParams['support_fapiao'] = $params['support_fapiao'];
            }
            // detail 选填 OrderDetail 优惠功能
            if (isset($params['detail']) && !empty($params['detail'])) {
                $businessParams['detail'] = $params['detail'];
            }
            // scene_info 选填 CommReqSceneInfo  场景信息
            if (isset($params['scene_info']) && !empty($params['scene_info'])) {
                $businessParams['scene_info'] = $params['scene_info'];
            }
            // settle_info 选填 SettleInfo  结算信息
            if (isset($params['settle_info']) && !empty($params['settle_info'])) {
                $businessParams['settle_info'] = $params['settle_info'];
            }
            $response = $this->instance
                ->chain('v3/pay/transactions/native')
                ->post(['json' => $businessParams]);
            $result = json_decode($response->getBody(), true);
            return $this->response($result);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }

    /**
     * 订单号查询
     * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/query-by-wx-trade-no.html
     * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/query-by-out-trade-no.html
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public function query(array $params)
    {
        try {
            // out_trade_no 和 trade_no 二选一
            if (!isset($params['out_trade_no']) && !isset($params['transaction_id'])) {
                throw new \InvalidArgumentException('商户订单号和微信交易号不能同时为空');
            }
            $path = '';
            if (isset($params['out_trade_no'])) {
                $path = 'v3/pay/transactions/out-trade-no/' . $params['out_trade_no'];
            }
            // 优先使用 transaction_id 查询
            if (isset($params['transaction_id'])) {
                $path = 'v3/pay/transactions/id/' . $params['transaction_id'];
            }
            $response = $this->instance
                ->chain($path)
                ->get(['query' => ['mchid' => $this->merchantId]]);
            $result = json_decode($response->getBody(), true);
            return $this->response($result);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }

    /**
     * 申请退款
     * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/create.html
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function refund(array $params)
    {
        try {
            $businessParams = [];
            // transaction_id 和 out_trade_no 二选一
            if (!isset($params['transaction_id']) && !isset($params['out_trade_no'])) {
                throw new \InvalidArgumentException('微信交易号和商户订单号不能同时为空');
            }
            if (isset($params['transaction_id'])) {
                $businessParams['transaction_id'] = $params['transaction_id'];
            }
            if (isset($params['out_trade_no'])) {
                $businessParams['out_trade_no'] = $params['out_trade_no'];
            }
            // out_refund_no string(64)  商户系统内部的退款单号，商户系统内部唯一，只能是数字、大小写字母_-|*@ ，同一退款单号多次请求只退一笔。
            if (!isset($params['out_refund_no']) || empty($params['out_refund_no'])) {
                throw new \InvalidArgumentException('商户退款单号不能为空');
            }
            $businessParams['out_refund_no'] = $params['out_refund_no'];
            // reason 选填 string(80) 退款原因
            if (isset($params['reason']) && !empty($params['reason'])) {
                $businessParams['reason'] = $params['reason'];
            }
            // notify_url 选填 string(255)  异步接收微信支付退款结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。 如果参数中传了notify_url，则商户平台上配置的回调地址将不会生效，优先回调当前传的这个地址。
            if (isset($params['notify_url']) && filter_var($params['notify_url'], FILTER_VALIDATE_URL)) {
                $businessParams['notify_url'] = $params['notify_url'];
            }
            // funds_account 选填 string(32) 退款资金来源
            if (isset($params['funds_account']) && !empty($params['funds_account'])) {
                $businessParams['funds_account'] = $params['funds_account'];
            }
            // amount 选填 AmountReq 退款金额信息
            if (isset($params['amount']) && !empty($params['amount'])) {
                // refund 必填 integer 退款金额，单位为分，只能为整数，不能超过原订单支付金额。
                if (!isset($params['amount']['refund']) || !is_numeric($params['amount']['refund'])) {
                    throw new \InvalidArgumentException('退款金额不能为空');
                }
                $businessParams['amount']['refund'] = $params['amount']['refund'];
                // from 选填 array[FundsFromItem]
                if (isset($params['amount']['from']) && !empty($params['amount']['from'])) {
                    $businessParams['amount']['from'] = $params['amount']['from'];
                }
                // total 选填 integer 订单总金额，单位为分，只能为整数，详见支付金额。
                if (!isset($params['amount']['total']) || empty($params['amount']['total'])) {
                    throw new \InvalidArgumentException('订单总金额不能为空');
                }
                $businessParams['amount']['total'] = $params['amount']['total'];
                // currency 选填 string(16) 符合ISO 4217标准的三位字母代码，人民币：CNY。
                if (!isset($params['amount']['currency']) || empty($params['amount']['currency'])) {
                    throw new \InvalidArgumentException('货币类型不能为空');
                }
                $businessParams['amount']['currency'] = $params['amount']['currency'];
            }
            // goods_detail 选填 array[GoodsDetail]
            if (isset($params['goods_detail']) && !empty($params['goods_detail'])) {
                $businessParams['goods_detail'] = $params['goods_detail'];
            }
            $response = $this->instance
                ->chain('v3/refund/domestic/refunds')
                ->post(['json' => $businessParams]);
            $result = json_decode($response->getBody(), true);
            return $this->response($result);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }

    /**
     * 退款查询
     * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/query-by-out-refund-no.html
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function refundQuery(array $params)
    {
        try {
            // out_request_no 必选 string(64) 】退款请求号。 请求退款接口时，传入的退款请求号，如果在退款请求时未传入，则该值为创建交易时的商户订单号。
            if (!isset($params['out_refund_no']) || empty($params['out_refund_no'])) {
                throw new \InvalidArgumentException('缺省商户退款单号');
            }
            $path = 'v3/refund/domestic/refunds/' . $params['out_refund_no'];
            $response = $this->instance
                ->chain($path)
                ->get();
            $result = json_decode($response->getBody(), true);
            return $this->response($result);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }

    /**
     * 微信-申请交易账单
     * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/download-bill.html
     * @param array $params
     * @return void
     * @throws \Exception
     */
    public function downloadBill(array $params)
    {
        /**
         * bill_date 必选 string(15)
         * 账单日期，格式yyyy-MM-DD，仅支持三个月内的账单下载申请
         */
        if (!isset($params['bill_date'])) {
            throw new \InvalidArgumentException('账单时间不能为空');
        }
        if (!isValidDateTime($params['bill_date'], 'Y-m-d')) {
            throw new \InvalidArgumentException('账单时间格式错误');
        }
        $businessOptions = [];
        $businessOptions['bill_date'] = $params['bill_date'];
        /**
         * bill_type 选填 string  账单类型，不填则默认是ALL
         * 【枚举值】
         * ALL: 返回当日所有订单信息（不含充值退款订单）
         * SUCCESS: 返回当日成功支付的订单（不含充值退款订单）
         * REFUND: 返回当日退款订单（不含充值退款订单）
         * RECHARGE_REFUND: 返回当日充值退款订单
         * ALL_SPECIAL: 返回个性化账单当日所有订单信息
         * SUC_SPECIAL: 返回个性化账单当日成功支付的订单
         * REF_SPECIAL: 返回个性化账单当日退款订单
         */
        if (isset($params['bill_type']) && !empty($params['bill_type'])) {
            $businessOptions['bill_type'] = $params['bill_type'];
        }
        // tar_type 选填 string  压缩类型，不填则默认是数据流, GZIP: GZIP格式压缩，返回格式为.gzip的压缩包账单
        if (isset($params['tar_type']) && !empty($params['tar_type'])) {
            $businessOptions['tar_type'] = $params['tar_type'];
        }
        $billDate = $params['bill_date'];
        $instance = $this->instance;
        $filePath = $params['file_path'] ?? '';
        $tarType = $businessOptions['tar_type'] ?? '';
        $this->instance->chain('/v3/bill/tradebill')->getAsync([
            'query' => $businessOptions,
        ])
            ->then(static function (ResponseInterface $response) use ($billDate): array {
                $target = (array)json_decode($response->getBody()->getContents(), true);
                return $target + ['bill_date' => $billDate];
            })
            ->then(static function (array $middle): array {
                $previous = new Uri($middle['download_url'] ?? '');
                $baseUri = $previous->composeComponents($previous->getScheme(), $previous->getAuthority(), '/', '', '');
                return $middle + [
                        'base_uri' => $baseUri, 'query' => $previous->getQuery(),
                        'pathname' => ltrim($previous->getPath(), '/')
                    ];
            })
            ->then(static function (array $download) use ($instance, $filePath): array {
                $handler = clone $instance->getDriver()->select()->getConfig('handler');
                $handler->remove('verifier');

                // 如果文件不为空则下载，否则直接输出内容
                $savedTo = Utils::tryFopen($filePath, 'w+');
                $stream = Utils::streamFor($savedTo);

                $instance
                    ->chain($download['pathname'])
                    ->get([
                        'sink' => $stream,
                        'handler' => $handler,
                        'query' => $download['query'],
                        'base_uri' => $download['base_uri'],
                    ]);
                return $download + ['stream' => $stream];
            })
            ->then(static function (array $verify) use ($tarType): ?string {
                $hashAlgo = strtolower($verify['hash_type'] ?? 'SHA1');
                $hashValue = $verify['hash_value'] ?? null;
                $stream = $verify['stream'];
                if ($tarType === 'GZIP') {
                    $signature = Utils::hash(new InflateStream($stream), $hashAlgo);
                } else {
                    $signature = Utils::hash($stream, $hashAlgo);
                }
                if (Hash::equals($signature, $hashValue)) {
                    $stream->close();
                    return true;
                }
                // TODO: 更多逻辑，比如验签失败，删除掉已存的文件等
                $stream->close();
                throw new \Exception('Bad digest verification');
            })
            ->wait();
    }

    /**
     * 微信-取消订单
     * @param array $order
     * @return mixed
     * @throws \Exception
     */
    public function cancel(array $order)
    {
        throw new \Exception('微信支付不支持取消订单');
        // TODO: Implement cancel() method.
    }

    /**
     * 支付宝-交易关闭接口
     * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/close-order.html
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function close(array $params)
    {
        try {
            // $subject 订单标题 string(256)
            if (!isset($params['out_trade_no']) || empty($params['out_trade_no'])) {
                throw new \InvalidArgumentException('缺省商户订单号');
            }
            // $outTradeNo 必填 string(32) 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
            if (!preg_match('/^[0-9a-zA-Z_\-*]{1,32}$/', $params['out_trade_no'])) {
                throw new \InvalidArgumentException('商户订单号格式错误');
            }
            $path = 'v3/pay/transactions/out-trade-no/' . $params['out_trade_no'] . '/close';
            $response = $this->instance
                ->chain($path)
                ->post(['json' => ['mchid' => $this->merchantId]]);
            $result = json_decode($response->getBody(), true);
            return $this->response($result);
        } catch (RequestException $e) {
            return $this->requestExceptionResponse($e);
        }
    }

    /**
     * 检查支付必要参数
     * @param array $params
     * @return array
     */
    protected function checkPayOptions(array $params): array
    {
        // $subject 订单标题 string(256)
        if (empty($params['subject'])) {
            throw new \InvalidArgumentException('缺省订单标题');
        }
        // $outTradeNo 必填 string(32) 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
        if (!isset($params['trade_no']) || (!preg_match('/^[0-9a-zA-Z_\-*]{1,32}$/', $params['trade_no']))) {
            throw new \InvalidArgumentException('商户订单号格式错误');
        }
        // $totalAmount 金额大于0
        if (!isset($params['amount']) || !is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new \InvalidArgumentException('订单金额为数字且大于0');
        }
        // notify_url 选填 string(255) 异步接收微信支付结果通知的回调地址，通知URL必须为外网可访问的URL，不能携带参数。 公网域名必须为HTTPS，如果是走专线接入，使用专线NAT IP或者私有回调域名可使用HTTP
        $customNotifyUrl = $this->notifyUrl;
        if (isset($params['notify_url']) && !empty($params['notify_url'])) {
            $customNotifyUrl = $params['notify_url'];
        }
        if (!filter_var($customNotifyUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('微信异步通知地址不合法');
        }
        return [$params['subject'], $params['trade_no'], $params['amount'], $customNotifyUrl];
    }

    /**
     * @param $data
     * @return Response
     */
    public function response($data): Response
    {
        return new Response(Response::STATUS_SUCCESS, 200, '', $data);
    }

    /**
     * @param RequestException $e
     * @return Response
     */
    public function requestExceptionResponse(RequestException $e): Response
    {
        if ($e->hasResponse()) {
            $r = $e->getResponse();
            $result = json_decode($r->getBody(), true);
            $code = $r->getStatusCode();
            $subCode = $result['code'] ?? '';
            $message = $result['message'] ?? '';
        } else {
            $code = $e->getCode();
            $subCode = 0;
            $message = $e->getMessage();
        }
        return new Response(Response::STATUS_ERROR, $code, $message, null, $subCode);
    }

    /**
     * @param INotify $notifyCallback
     * @return void
     */
    public function notify(INotify $notifyCallback)
    {
        [$serialNo, $signature, $nonce, $timestamp, $requestID, $body] = $this->getNotifyData();
        if (empty($body)) {
            throw new \InvalidArgumentException('缺省通知数据');
        }
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$timestamp);
        if ($timeOffsetStatus) {
            throw new \InvalidArgumentException('时间戳超过5分钟');
        }
        if ($serialNo != $this->platformCertificateSerial) {
            throw new \InvalidArgumentException('证书序列号不匹配');
        }
        $verifiedStatus = Rsa::verify(
            Formatter::joinedByLineFeed($timestamp, $nonce, $body),
            $signature,
            $this->platformPublicKeyInstance
        );
        if (!$verifiedStatus) {
            throw new \InvalidArgumentException('异步通知签名校验失败');
        }
        $inBodyArray = (array)json_decode($body, true);
        if (empty($inBodyArray)) {
            throw new \InvalidArgumentException('异步通知数据解析失败');
        }
        // 获取通知类型
        if (empty($inBodyArray['event_type'])) {
            throw new \InvalidArgumentException('缺省异步通知类型');
        }
        ['resource' => [
            'ciphertext' => $ciphertext,
            'nonce' => $nonce,
            'associated_data' => $aad
        ]] = $inBodyArray;
        $inBodyResource = AesGcm::decrypt($ciphertext, $this->apiV3Key, $nonce, $aad);
        $notifyData = (array)json_decode($inBodyResource, true);
        $notifyType = $this->getNotifyType($inBodyArray['event_type']);
        $classNotifyType = $notifyCallback->getNotifyType();
        if ($classNotifyType == $notifyType) {
            $result = $notifyCallback->handle(self::SP_NAME, $notifyData);
            exit($this->notifyResponse($result));
        }
        throw new \InvalidArgumentException('异步通知类型与回调处理类型不匹配');
    }

    /**
     * 获取异步通知数据
     * 请求头部Headers，拿到Wechatpay-Signature、Wechatpay-Nonce、Wechatpay-Timestamp、Wechatpay-Serial及Request-ID，商户侧Web解决方案可能有差异，请求头可能大小写不敏感，请根据自身应用来定；
     * @return array
     */
    public function getNotifyData(): array
    {
        $body = @file_get_contents('php://input');
        return [
            $_SERVER['Wechatpay-Serial'] ?? '',
            $_SERVER['Wechatpay-Signature'] ?? '',
            $_SERVER['Wechatpay-Nonce'] ?? '',
            $_SERVER['Wechatpay-Timestamp'] ?? 0,
            $_SERVER['Request-ID'] ?? '',
            $body,
        ];
    }

    /**
     * @param $notifyType
     * @return string
     */
    protected function getNotifyType($notifyType): string
    {
        switch ($notifyType) {
            case 'TRANSACTION.SUCCESS':
                return INotify::NOTIFY_PAY;
            case 'REFUND.SUCCESS':
                return INotify::NOTIFY_REFUND;
            default:
                throw new \InvalidArgumentException('异步通知类型未知');
        }
    }

    /**
     * @link https://pay.weixin.qq.com/docs/merchant/apis/jsapi-payment/payment-notice.html
     * 接收成功： HTTP应答状态码需返回200或204，无需返回应答报文。
     * 接收失败： HTTP应答状态码需返回5XX或4XX，同时需返回应答报文
     * @param bool $status
     * @return string
     */
    public function notifyResponse(bool $status): string
    {
        $data = '';
        if (!$status) {
            // 强制返回500
            header('HTTP/1.1 500 Internal Server Error');
            $data = json_encode([
                'code' => 'FAIL',
                'message' => 'mch have an errors',
            ]);
        } else {
            header('HTTP/1.1 200 OK');
        }
        return $data;
    }
}