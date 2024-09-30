<?php

namespace Simplephp\PaymentSdk\Provider;

use AlibabaCloud\Tea\Model;
use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\MultipleFactory;
use Simplephp\PaymentSdk\Abstracts\APayment;
use Simplephp\PaymentSdk\Abstracts\APayNotify;
use Simplephp\PaymentSdk\Contracts\INotify;
use Simplephp\PaymentSdk\Models\Response;

/**
 * Class Alipay
 * @package Simplephp\PaymentSdk\Provider
 */
class Alipay extends APayment
{
    /**
     * 服务商名称
     */
    const SP_NAME = 'alipay';

    /**
     *  支付配置信息
     * @var $config
     */
    protected $config;

    /**
     * @var $instance MultipleFactory
     */
    protected $instance;

    /**
     * @var string
     */
    private $appId;

    /**
     * @var string
     */
    private $productCode;
    /**
     * @var string
     */
    private $signScene;

    /**
     * @var string
     */
    private $personalProductCode;


    /**
     * product_code 周期扣款(比较早的一种扣款方式)
     */
    const CYCLE_PAY_AUTH = 'CYCLE_PAY_AUTH';

    /**
     * product_code 商家扣款
     */
    const GENERAL_WITHHOLDING = 'GENERAL_WITHHOLDING';

    /**
     * channel 接入方式 钱包h5页面签约
     */
    const ALIPAYAPP_CHANNEL = 'ALIPAYAPP';

    /**
     * channel 接入方式 扫码签约
     */
    const QRCODE_CHANNEL = 'QRCODE';
    /**
     * channel 接入方式 扫码签约或者短信签约
     */
    const QRCODEORSMS_CHANNEL = 'QRCODEORSMS';

    /**
     * period_type 周期类型
     */
    const PERIOD_TYPE_DAY = 'DAY';

    /**
     * period_type 周期类型
     */
    const PERIOD_TYPE_MONTH = 'MONTH';

    /**
     * @var string
     */
    const AGREEMENT_SIGN = 'alipays://platformapi/startapp?appId=60000157&appClearTop=false&startMultApp=YES&sign_params=';


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
        $appId = $config['app_id'] ?? '';
        if (empty($appId)) {
            throw new \InvalidArgumentException('支付宝APPID不能为空');
        }
        $this->appId = $appId;
        $appPrivateKey = $config['app_private_key'] ?? '';
        if (empty($appPrivateKey)) {
            throw new \InvalidArgumentException('支付宝应用私钥不能为空');
        }
        // 支付宝参数 alipay_public_key 和 （$alipayPublicCertPath、$alipayRootCertPath、$appPublicCertPath） 二选一
        $alipayPublicKey = $config['alipay_public_key'] ?? '';
        $alipayPublicCertPath = $config['alipay_public_cert_path'] ?? '';
        $alipayRootCertPath = $config['alipay_root_cert_path'] ?? '';
        $appPublicCertPath = $config['app_public_cert_path'] ?? '';
        if (empty($alipayPublicKey) && (empty($alipayPublicCertPath) || empty($alipayRootCertPath) || empty($appPublicCertPath))) {
            throw new \InvalidArgumentException('支付宝参数 alipay_public_key 和 （alipay_public_cert_path、alipay_root_cert_path、app_public_cert_path）二选一');
        }
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';
        $options->appId = $appId;
        // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
        $options->merchantPrivateKey = $appPrivateKey;
        if ($alipayPublicKey) {
            $options->alipayPublicKey = $alipayPublicKey;
        } else {
            $options->alipayCertPath = $alipayPublicCertPath; // '<-- 请填写您的支付宝公钥证书文件路径，例如：/foo/alipayCertPublicKey_RSA2.crt -->';
            $options->alipayRootCertPath = $alipayRootCertPath; //  '<-- 请填写您的支付宝根证书文件路径，例如：/foo/alipayRootCert.crt" -->';
            $options->merchantCertPath = $appPublicCertPath; // '<-- 请填写您的应用公钥证书文件路径，例如：/foo/appCertPublicKey_2019051064521003.crt -->';
        }
        $notifyUrl = $config['notify_url'] ?? '';
        if ($notifyUrl) {
            $options->notifyUrl = $notifyUrl;
        }
        ///可设置AES密钥，调用AES加解密相关接口时需要（可选）
        $encryptKey = $config['encrypt_key'] ?? '';
        if ($encryptKey) {
            $options->encryptKey = $encryptKey;
        }
        //【描述】商家和支付宝签约的产品码：CYCLE_PAY_AUTH（周期扣款，比较老的产品编码） GENERAL_WITHHOLDING（商家扣款）
        $this->productCode = $config['product_code'] ?? '';
        //【描述】协议签约场景，商户和支付宝签约时确定sign_scene INDUSTRY|DIGITAL_MEDIA
        $this->signScene = $config['sign_scene'] ?? '';
        //【描述】个人签约产品码，商户和支付宝签约时确定，商户可咨询技术支持
        $this->personalProductCode = $config['personal_product_code'] ?? '';
        $this->instance = MultipleFactory::setOptions($options);
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
            case 'ALIPAY_APP_PAY':
                return $this->appPay($params);
            case 'ALIPAY_APP_PAY_SIGN':
                return $this->appPaySign($params);
            case 'ALIPAY_WAP_PAY':
                return $this->wapPay($params);
            case 'ALIPAY_WEB_PAY':
                return $this->webPay($params);
            case 'ALIPAY_QR_PAY':
                return $this->qrPay($params);
            default:
                throw new \Exception('支付方式不存在');
        }
    }

    /**
     * 支付宝-app支付
     * @link https://opendocs.alipay.com/open/cd12c885_alipay.trade.app.pay
     * @param array $params
     * @return string
     */
    public function appPay(array $params)
    {
        [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
        $businessOptions = [];
        if (isset($params['notify_url'])) {
            $businessOptions['notify_url'] = $params['notify_url'];
        }
        if (isset($params['product_code'])) {
            $businessOptions['product_code'] = $params['product_code'];
        }
        // goods_detail 可选GoodsDetail[] 订单包含的商品列表信息，json格式，其它说明详见商品明细说明
        if (isset($params['goods_detail'])) {
            $goodsDetail = $this->checkGoodsDetail($params['goods_detail']);
            $businessOptions['goods_detail'] = $goodsDetail;
        }
        // time_expire 可选 string(32) 绝对超时时间，格式为yyyy-MM-dd HH:mm
        if (isset($params['time_expire'])) {
            $businessOptions['time_expire'] = $params['time_expire'];
        }
        // extend_params 可选ExtendParams 业务扩展参数
        if (isset($params['extend_params'])) {
            $businessOptions['extend_params'] = $params['extend_params'];
        }
        // passback_params 可选 string(512) 公用回传参数，如果请求时传递了该参数，则返回给商户时会回传该参数。支付宝只会在同步返回（包括跳转回商户网站）和异步通知时将该参数原样返回。本参数必须进行UrlEncode之后才可以发送给支付宝。
        if (isset($params['passback_params'])) {
            $businessOptions['passback_params'] = $params['passback_params'];
        }
        // merchant_order_no 可选 string(32) 商户原始订单号，最大长度限制32位
        if (isset($params['merchant_order_no'])) {
            $businessOptions['merchant_order_no'] = $params['merchant_order_no'];
        }
        // ext_user_info 可选 ExtUserInfo 外部指定买家
        if (isset($params['ext_user_info'])) {
            $businessOptions['ext_user_info'] = $params['ext_user_info'];
        }
        // query_options 可选 string[](1024) 返回参数选项。 商户通过传递该参数来定制同步需要额外返回的信息字段，数组格式。包括但不限于：["hyb_amount","enterprise_pay_info"]
        if (isset($params['query_options'])) {
            $businessOptions['query_options'] = $params['query_options'];
        }
        return $this->appPayExecute($subject, $outTradeNo, $totalAmount, $businessOptions);
    }

    /**
     * 支付宝-app支付并签约
     * @link https://opendocs.alipay.com/open/e65d4f60_alipay.trade.app.pay
     * @param array $params
     * @return string
     */
    public function appPaySign(array $params)
    {
        [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
        // time_expire 订单过期时间，格式为yyyy-MM-dd HH:mm:ss。注：time_expire和timeout_express两者只需传入一个或者都不传，如果两者都传，优先使用time_expire。
        $businessOptions = [];
        if (isset($params['product_code'])) {
            $businessOptions['product_code'] = $params['product_code'];
        }
        if (isset($params['time_expire'])) {
            if (!isValidDateTime($params['time_expire'])) {
                throw new \InvalidArgumentException('订单绝对超时时间格式错误');
            }
            $businessOptions['time_expire'] = $params['time_expire'];
        }
        // 签约类型
        $agreementSignTmpParams = $params['agreement_sign_params'] ?? [];
        if (!empty($agreementSignTmpParams)) {
            $agreementSignParams = $this->checkAgreementOptions($agreementSignTmpParams);
            if (empty($this->productCode) && empty($agreementSignTmpParams['product_code'])) {
                throw new \InvalidArgumentException('请配置支付宝签约产品码');
            }
            $productCode = $agreementSignTmpParams['product_code'] ?? $this->productCode;
            // 如果是商家扣款类型，需要额外在 agreement_sign_params 中增加 product_code 参数，周期扣款不需要
            if ($productCode == self::GENERAL_WITHHOLDING) {
                $agreementSignParams['product_code'] = $productCode;
            }
            // external_logon_id 【描述】用户在商户网站的登录账号，用于在签约页面展示，如果为空，则不展示-可选。如果为空，则不展示
            if (isset($agreementSignTmpParams['external_logon_id'])) {
                $agreementSignParams['external_logon_id'] = $agreementSignTmpParams['external_logon_id'];
            }
            // sign_notify_url 【描述】签约成功后商户用于接收异步通知的地址-可选。如果不传入，签约与支付的异步通知都会发到外层notify_url参数传入的地址；如果外层也未传入，签约与支付的异步通知都会发到商户appid配置的网关地址。
            if (isset($agreementSignTmpParams['sign_notify_url'])) {
                if (!filter_var($agreementSignTmpParams['sign_notify_url'], FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException('支付宝签约异步通知地址格式错误');
                }
                $agreementSignParams['sign_notify_url'] = $agreementSignTmpParams['sign_notify_url'];
            }
            $businessOptions['agreement_sign_params'] = $agreementSignParams;
        }
        return $this->appPayExecute($subject, $outTradeNo, $totalAmount, $businessOptions);
    }

    /**
     * 支付宝-wap支付
     * @link https://opendocs.alipay.com/open/02np8y?pathHash=718b8786
     * @param $params
     * @return string
     */
    public function wapPay($params)
    {
        [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
        // time_expire 订单过期时间，格式为yyyy-MM-dd HH:mm:ss。注：time_expire和timeout_express两者只需传入一个或者都不传，如果两者都传，优先使用time_expire。
        $businessOptions = [];
        if (isset($params['product_code'])) {
            $businessOptions['product_code'] = $params['product_code'];
        }
        // auth_token 【可选】可选string(40) 针对用户授权接口，获取用户相关数据时，用于标识用户授权关系
        if (isset($params['auth_token'])) {
            $businessOptions['auth_token'] = $params['auth_token'];
        }
        // body 【可选】string(128) 订单附加信息。 如果请求时传递了该参数，将在异步通知、对账单中原样返回，同时会在商户和用户的pc账单详情中作为交易描述展示
        //【示例值】Iphone6 16G
        if (isset($params['body'])) {
            $businessOptions['body'] = $params['body'];
        }
        // quit_url 【可选】 string(400) 【描述】用户付款中途退出返回商户网站的地址
        if (isset($params['quit_url'])) {
            $businessOptions['quit_url'] = $params['quit_url'];
        }
        // goods_detail 【可选】GoodsDetail[] 订单包含的商品列表信息，Json格式，其它说明详见商品明细说明
        if (isset($params['goods_detail']) && is_array($params['goods_detail'])) {
            $businessOptions['goods_detail'] = $this->checkGoodsDetail($params['goods_detail']);
        }
        //time_expire 可选 string(32) 订单绝对超时时间。 格式为yyyy-MM-dd HH:mm:ss。超时时间范围：1m~15d。 注：time_express和timeout_express两者只需传入一个或者都不传，如果两者都传，优先使用time_expire。
        if (isset($params['time_expire'])) {
            if (!isValidDateTime($params['time_expire'])) {
                throw new \InvalidArgumentException('订单绝对超时时间格式错误');
            }
        }
        // extend_params 可选 ExtendParams 业务扩展参数
        if (isset($params['extend_params']) && is_array($params['extend_params'])) {
            $businessOptions['extend_params'] = $params['extend_params'];
        }
        // business_params 可选 string(512) 商户传入业务信息，具体值要和支付宝约定，应用于安全，营销等参数直传场景，格式为json格式
        if (isset($params['business_params'])) {
            $businessOptions['business_params'] = $params['business_params'];
        }
        // passback_params 可选 string (512) 公用回传参数，如果请求时传递了该参数，则返回给商户时会回传该参数。支付宝只会在同步返回（包括跳转回商户网站）和异步通知时将该参数原样返回。本参数必须进行UrlEncode之后才可以发送给支付宝。
        if (isset($params['passback_params'])) {
            $businessOptions['passback_params'] = $params['passback_params'];
        }
        // merchant_order_no 可选 string(32) 商户原始订单号，最大长度限制32位
        if (isset($params['merchant_order_no'])) {
            $businessOptions['merchant_order_no'] = $params['merchant_order_no'];
        }
        // ext_user_info 可选 ExtUserInfo 外部指定买家
        if (isset($params['ext_user_info']) && is_array($params['ext_user_info'])) {
            $businessOptions['ext_user_info'] = $params['ext_user_info'];
        }
        $paymentWap = $this->instance::payment()->wap();
        // 如果配置设置：notify_url，当前参数设置 notify_url 不会生效～
        if (isset($params['notify_url']) && (false !== filter_var($params['notify_url'], FILTER_VALIDATE_URL))) {
            $paymentWap = $paymentWap->asyncNotify($params['notify_url']);
        }
        if (!empty($businessOptions)) {
            $paymentWap = $paymentWap->batchOptional($businessOptions);
        }
        $quitUrl = $params['quit_url'] ?? '';
        $returnUrl = $params['return_url'] ?? '';
        return $paymentWap->pay($subject, $outTradeNo, $totalAmount, $quitUrl, $returnUrl)->body;
    }

    /**
     * 支付宝-web支付
     * @link https://opendocs.alipay.com/open/59da99d0_alipay.trade.page.pay?scene=22&pathHash=e26b497f
     * @param $params
     * @return string
     */
    public function webPay($params)
    {
        [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
        // time_expire 订单过期时间，格式为yyyy-MM-dd HH:mm:ss。注：time_expire和timeout_express两者只需传入一个或者都不传，如果两者都传，优先使用time_expire。
        $businessOptions = [];
        if (!isset($params['product_code'])) {
            throw new \InvalidArgumentException('product_code销售产品码不能为空');
        }
        $businessOptions['product_code'] = $params['product_code'];
        /**
         * qr_pay_mode 【可选】可选string(2) 支持前置模式和跳转模式。
         * 前置模式是将二维码前置到商户的订单确认页的模式。需要商户在自己的页面中以 iframe 方式请求支付宝页面。具体支持的枚举值有以下几种：
         * 0：订单码-简约前置模式，对应 iframe 宽度不能小于600px，高度不能小于300px；
         * 1：订单码-前置模式，对应iframe 宽度不能小于 300px，高度不能小于600px；
         * 3：订单码-迷你前置模式，对应 iframe 宽度不能小于 75px，高度不能小于75px；
         * 4：订单码-可定义宽度的嵌入式二维码，商户可根据需要设定二维码的大小。
         * 跳转模式下，用户的扫码界面是由支付宝生成的，不在商户的域名下。支持传入的枚举值有：
         * 2：订单码-跳转模式
         * 【枚举值】
         * 订单码-简约前置模式: 0
         * 订单码-前置模式: 1
         * 订单码-迷你前置模式: 3
         * 订单码-可定义宽度的嵌入式二维码: 4
         */
        if (isset($params['qr_pay_mode'])) {
            $businessOptions['qr_pay_mode'] = $params['qr_pay_mode'];
        }
        // qrcode_width 【可选】number(4) 商户自定义二维码宽度。注：qr_pay_mode=4时该参数有效
        if (isset($params['qrcode_width'])) {
            $businessOptions['qrcode_width'] = $params['qrcode_width'];
        }
        // goods_detail 【可选】GoodsDetail[] 订单包含的商品列表信息，Json格式，其它说明详见商品明细说明
        if (isset($params['goods_detail']) && is_array($params['goods_detail'])) {
            $businessOptions['goods_detail'] = $this->checkGoodsDetail($params['goods_detail']);
        }
        //time_expire 可选 string(32) 订单绝对超时时间。 格式为yyyy-MM-dd HH:mm:ss。超时时间范围：1m~15d。 注：time_express和timeout_express两者只需传入一个或者都不传，如果两者都传，优先使用time_expire。
        if (isset($params['time_expire'])) {
            if (!isValidDateTime($params['time_expire'])) {
                throw new \InvalidArgumentException('订单绝对超时时间格式错误');
            }
        }
        // sub_merchant 可选 SubMerchant 二级商户信息。直付通模式和机构间连模式下必传，其它场景下不需要传入。
        if (isset($params['sub_merchant']) && is_array($params['sub_merchant'])) {
            // merchant_id 必选 string(16)  间连受理商户的支付宝商户编号，通过间连商户入驻后得到。间连业务下必传，并且需要按规范传递受理商户编号。
            if (!isset($params['sub_merchant']['merchant_id'])) {
                throw new \InvalidArgumentException('sub_merchant.merchant_id 间连受理商户的支付宝商户编号不能为空');
            }
            $businessOptions['sub_merchant'] = $params['sub_merchant'];
        }
        // business_params 可选 string(512) 商户传入业务信息，具体值要和支付宝约定，应用于安全，营销等参数直传场景，格式为json格式
        if (isset($params['business_params'])) {
            $businessOptions['business_params'] = $params['business_params'];
        }
        // promo_params 可选 string (512) 优惠参数。为 JSON 格式。注：仅与支付宝协商后可用 【示例值】{"storeIdType":"1"}
        if (isset($params['promo_params'])) {
            $businessOptions['promo_params'] = $params['promo_params'];
        }
        /**
         * integration_type 可选string(16)
         * 【描述】请求后页面的集成方式。
         * 枚举值：
         *  ALIAPP：支付宝钱包内
         *  PCWEB：PC端访问
         *  默认值为PCWEB。
         * 【枚举值】
         * 支付宝钱包内: ALIAPP
         * PC端访问: PCWEB
         */
        if (isset($params['integration_type'])) {
            $businessOptions['integration_type'] = $params['integration_type'];
        }
        // request_from_url 可选 string(256) 请求来源地址。如果使用ALIAPP的集成方式，用户中途取消支付会返回该地址。https://
        if (isset($params['request_from_url'])) {
            $businessOptions['request_from_url'] = $params['request_from_url'];
        }
        // store_id 可选 string (32) 商户门店编号。指商户创建门店时输入的门店编号。【示例值】NJ_001
        if (isset($params['store_id'])) {
            $businessOptions['store_id'] = $params['store_id'];
        }
        // merchant_order_no 可选 string (32) 商户原始订单号，最大长度限制 32 位
        if (isset($params['merchant_order_no'])) {
            $businessOptions['merchant_order_no'] = $params['merchant_order_no'];
        }
        // ext_user_info 可选 ExtUserInfo 外部指定买家
        if (isset($params['ext_user_info']) && is_array($params['ext_user_info'])) {
            $businessOptions['ext_user_info'] = $params['ext_user_info'];
        }
        // invoice_info 可选 InvoiceInfo 发票信息
        if (isset($params['invoice_info']) && is_array($params['invoice_info'])) {
            $businessOptions['invoice_info'] = $params['invoice_info'];
        }
        $paymentWeb = $this->instance::payment()->page();
        // 如果配置设置：notify_url，当前参数设置 notify_url 不会生效～
        if (isset($params['notify_url']) && (false !== filter_var($params['notify_url'], FILTER_VALIDATE_URL))) {
            $paymentWeb = $paymentWeb->asyncNotify($params['notify_url']);
        }
        if (!empty($businessOptions)) {
            $paymentWeb = $paymentWeb->batchOptional($businessOptions);
        }
        $returnUrl = $params['return_url'] ?? '';
        return $paymentWeb->pay($subject, $outTradeNo, $totalAmount, $returnUrl)->body;
    }

    /**
     * 支付宝-QR支付,生成交易付款码，待用户扫码付款
     * @link https://opendocs.alipay.com/open/8ad49e4a_alipay.trade.precreate
     * @param $params
     * @return Response
     * @throws \Exception
     */
    public function qrPay($params)
    {
        [$subject, $outTradeNo, $totalAmount] = $this->checkPayOptions($params);
        // time_expire 订单过期时间，格式为yyyy-MM-dd HH:mm:ss。注：time_expire和timeout_express两者只需传入一个或者都不传，如果两者都传，优先使用time_expire。
        $businessOptions = [];
        if (!isset($params['product_code'])) {
            throw new \InvalidArgumentException('product_code销售产品码不能为空');
        }
        $businessOptions['product_code'] = $params['product_code'];
        /**
         * seller_id 可选 string(32) 【描述】卖家支付宝用户ID。
         * 当需要指定收款账号时，通过该参数传入，如果该值为空，则默认为商户签约账号对应的支付宝用户ID。
         * 收款账号优先级规则：门店绑定的收款账户>请求传入的seller_id>商户签约账号对应的支付宝用户ID；
         * 注：直付通和机构间联场景下seller_id无需传入或者保持跟pid一致；
         * 如果传入的seller_id与pid不一致，需要联系支付宝小二配置收款关系；
         */
        if (isset($params['seller_id'])) {
            $businessOptions['seller_id'] = $params['seller_id'];
        }
        /**
         * body可选string(128)
         * 【描述】订单附加信息。
         *  如果请求时传递了该参数，将在异步通知、对账单中原样返回，同时会在商户和用户的pc账单详情中作为交易描述展示
         * 【示例值】Iphone6 16G
         */
        if (isset($params['body'])) {
            $businessOptions['body'] = $params['body'];
        }
        // goods_detail可选GoodsDetail[]
        //【描述】订单包含的商品列表信息.json格式. 其它说明详见：“商品明细说明”
        if (isset($params['goods_detail']) && is_array($params['goods_detail'])) {
            $businessOptions['goods_detail'] = $this->checkGoodsDetail($params['goods_detail']);
        }
        // extend_params 可选 ExtendParams 【描述】业务扩展参数
        if (isset($params['extend_params']) && is_array($params['extend_params'])) {
            $businessOptions['extend_params'] = $params['extend_params'];
        }
        // business_params 可选 BusinessParams 商户传入业务信息，具体值要和支付宝约定，应用于安全，营销等参数直传场景，格式为json格式
        if (isset($params['business_params']) && is_array($params['business_params'])) {
            $businessOptions['business_params'] = $params['business_params'];
        }
        /**
         * discountable_amount 可选 string(9) 订单可打折金额。
         * 【描述】可打折金额。
         * 参与优惠计算的金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]。
         * 如果同时传入了【可打折金额】、【不可打折金额】和【订单总金额】，则必须满足如下条件：【订单总金额】=【可打折金额】+【不可打折金额】。
         * 如果订单金额全部参与优惠计算，则【可打折金额】和【不可打折金额】都无需传入。
         * 【示例值】80.00
         */
        if (isset($params['discountable_amount'])) {
            $businessOptions['discountable_amount'] = $params['discountable_amount'];
        }
        // store_id 可选 string(32) 【描述】商户门店编号
        if (isset($params['store_id'])) {
            $businessOptions['store_id'] = $params['store_id'];
        }
        // operator_id 可选 string(28) 【描述】商户操作员编号。
        if (isset($params['operator_id'])) {
            $businessOptions['operator_id'] = $params['operator_id'];
        }
        // terminal_id 可选 string(32) 商户机具终端编号。
        if (isset($params['terminal_id'])) {
            $businessOptions['terminal_id'] = $params['terminal_id'];
        }
        // merchant_order_no 可选 string(32) 【描述】商户的原始订单号
        if (isset($params['merchant_order_no'])) {
            $businessOptions['merchant_order_no'] = $params['merchant_order_no'];
        }
        $paymentWeb = $this->instance::payment()->FaceToFace();
        // 如果配置设置：notify_url，当前参数设置 notify_url 不会生效～
        if (isset($params['notify_url']) && (false !== filter_var($params['notify_url'], FILTER_VALIDATE_URL))) {
            $paymentWeb = $paymentWeb->asyncNotify($params['notify_url']);
        }
        if (!empty($businessOptions)) {
            $paymentWeb = $paymentWeb->batchOptional($businessOptions);
        }
        $response = $paymentWeb->preCreate($subject, $outTradeNo, $totalAmount);
        return $this->createResponse('alipay.trade.precreate', $response);
    }

    /**
     * 交易查询
     * @link https://opendocs.alipay.com/open/82ea786a_alipay.trade.query
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public function query(array $params)
    {
        // out_trade_no 和 trade_no 二选一
        if (!isset($params['out_trade_no']) && !isset($params['trade_no'])) {
            throw new \InvalidArgumentException('商户订单号和支付宝交易号不能同时为空');
        }
        $businessOptions = [];
        if (isset($params['trade_no'])) {
            $businessOptions['trade_no'] = trim($params['trade_no']);
        }
        // org_pid 可选 string(16) 【描述】银行间联模式下有用，其它场景请不要使用；
        if (isset($params['org_pid'])) {
            $businessOptions['org_pid'] = $params['org_pid'];
        }
        // query_options 可选 string(32) 【描述】查询选项，商户通过上送该参数来定制查询返回信息
        if (isset($params['query_options'])) {
            $businessOptions['query_options'] = $params['query_options'];
        }
        $payment = $this->instance::payment()->common();
        if (!empty($businessOptions)) {
            $payment = $payment->batchOptional($businessOptions);
        }
        $outTradeNo = $params['out_trade_no'] ?? '';
        $response = $payment->query($outTradeNo);
        return $this->createResponse('alipay.trade.query', $response);
    }

    /**
     * 支付宝-独立签约后扣款
     * @param array $params
     * @return string
     */
    public function agreementSign(array $params)
    {
        $agreementSignParams = $this->checkAgreementOptions($params);
        // product_code 销售产品码，商户签约的支付宝合同所对应的产品码。
        $agreementSignParams['product_code'] = $agreementSignTmpParams['product_code'] ?? $this->productCode;
        // third_party_type 【签约第三方主体类型。对于三方协议，表示当前用户和哪一类的第三方主体进行签约。 默认为PARTNER。
        if (isset($params['third_party_type'])) {
            $agreementSignParams['third_party_type'] = $params['third_party_type'];
        }
        // sign_validity_period 当前用户签约请求的协议有效周期。 整形数字加上时间单位的协议有效期，从发起签约请求的时间开始算起。 目前支持的时间单位： 1. d：天 2. m：月 如果未传入，默认为长期有效
        if (isset($params['sign_validity_period'])) {
            $agreementSignParams['sign_validity_period'] = $params['sign_validity_period'];
        }
        // zm_auth_params 【描述】芝麻授权信息，针对于信用代扣签约。json格式。
        if (isset($params['zm_auth_params'])) {
            $agreementSignParams['zm_auth_params'] = $params['zm_auth_params'];
        }
        // prod_params 【描述】签约产品属性，json格式。
        if (isset($params['prod_params'])) {
            $agreementSignParams['prod_params'] = $params['prod_params'];
        }
        // promo_params  签约营销参数，此值为json格式；具体的key需与营销约定
        if (isset($params['promo_params'])) {
            $agreementSignParams['promo_params'] = $params['promo_params'];
        }
        // sub_merchant 此参数用于传递子商户信息，无特殊需求时不用关注。目前商户代扣、海外代扣、淘旅行信用住产品支持传入该参数（在销售方案中“是否允许自定义子商户信息”需要选是）。
        if (isset($params['sub_merchant'])) {
            $agreementSignParams['sub_merchant'] = $params['sub_merchant'];
        }
        // device_params 【描述】设备信息参数，在使用设备维度签约代扣协议时，可以传这些信息
        if (isset($params['device_params'])) {
            $agreementSignParams['device_params'] = $params['device_params'];
        }
        // identity_params 【描述】 用户实名信息参数，包含：姓名、身份证号、签约指定uid。商户传入用户实名信息参数，支付宝会对比用户在支付宝端的实名信息
        if (isset($params['identity_params'])) {
            $agreementSignParams['identity_params'] = $params['identity_params'];
        }
        /**
         * agreement_effect_type 协议生效类型, 用于指定协议是立即生效还是等待商户通知再生效. 可空, 不填默认为立即生效.
         * 【枚举值】
         * 立即生效: DIRECT
         * 商户通知生效, 需要再次调用alipay.user.agreement.sign.effect （支付宝个人协议签约生效接口）接口推进协议生效.: NOTICE
         * 允许变更状态: ALLOW_INACTIVATE
         */
        if (isset($params['agreement_effect_type'])) {
            $agreementSignParams['agreement_effect_type'] = $params['agreement_effect_type'];
        }
        // user_age_range 商户希望限制的签约用户的年龄范围，min表示可签该协议的用户年龄下限，max表示年龄上限。如{"min": "18","max": "30"}表示18=<年龄<=30的用户可以签约该协议。
        if (isset($params['user_age_range'])) {
            $agreementSignParams['user_age_range'] = $params['user_age_range'];
        }
        // effect_time 签约有效时间限制，单位是秒，有效范围是0-86400，商户传入此字段会用商户传入的值否则使用支付宝侧默认值，在有效时间外进行签约，会进行安全拦截；（备注：此字段适用于需要开通安全防控的商户，且依赖商户传入生成签约时的时间戳字段timestamp）
        if (isset($params['effect_time'])) {
            $agreementSignParams['effect_time'] = $params['effect_time'];
        }
        // sign_notify_url 【描述】签约成功后商户用于接收异步通知的地址-可选。如果不传入，签约与支付的异步通知都会发到外层notify_url参数传入的地址；如果外层也未传入，签约与支付的异步通知都会发到商户appid配置的网关地址。
        if (isset($agreementSignTmpParams['return_url'])) {
            if (!filter_var($agreementSignTmpParams['return_url'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('支付宝签约同步通知地址格式错误');
            }
            $agreementSignParams['return_url'] = $agreementSignTmpParams['return_url'];
        }
        if (isset($agreementSignTmpParams['notify_url'])) {
            if (!filter_var($agreementSignTmpParams['notify_url'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('支付宝签约异步通知地址格式错误');
            }
            $agreementSignParams['return_url'] = $agreementSignTmpParams['notify_url'];
        }
        // 签约类型 @link https://opendocs.alipay.com/support/01rg2c
        if ($params['access_params']['channel'] == self::ALIPAYAPP_CHANNEL || $params['access_params']['channel'] == self::QRCODE_CHANNEL) {
            $signParams = $this->instance::util()->generic()->sdkExecute('alipay.user.agreement.page.sign', [], $agreementSignParams)->body;
            return self::AGREEMENT_SIGN . urlencode($signParams);
        } else {
            $agreementSignParams['access_params']['channel'] = self::ALIPAYAPP_CHANNEL;
            return $this->instance::util()->generic()->generatePage('alipay.user.agreement.page.sign', $agreementSignParams, [], 'GET')->body;
        }
    }


    /**
     * 支付宝-个人代扣协议查询
     * @link https://opendocs.alipay.com/open/3dab71bc_alipay.user.agreement.query
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function agreementQuery(array $params)
    {
        $businessOptions = [];
        // alipay_user_id string(32) 用户的支付宝账号对应 的支付宝唯一用户号，以 2088 开头的 16 位纯数字 组成。 本参数与alipay_logon_id若都填写，则以本参数为准，优先级高于 alipay_logon_id
        if (isset($params['alipay_user_id'])) {
            $businessOptions['alipay_user_id'] = $params['alipay_user_id'];
        }
        // alipay_open_id string(128) 用户的支付宝账号对应 的支付宝唯一用户号， 本参数与alipay_logon_id若都填写，则以本参数为准，优先级高于 alipay_logon_id
        if (isset($params['alipay_open_id'])) {
            $businessOptions['alipay_open_id'] = $params['alipay_open_id'];
        }
        // alipay_logon_id string(100) 用户的支付宝登录账号，支持邮箱或手机号码格式。本参数与alipay_open_id 或 alipay_user_id 同时填写，优先按照 alipay_open_id 或 alipay_user_id 处理。
        if (isset($params['alipay_logon_id'])) {
            $businessOptions['alipay_logon_id'] = $params['alipay_logon_id'];
        }
        // personal_product_code string(64) 个人签约产品码，商户和支付宝签约时确定。 示例值 GENERAL_WITHHOLDING
        if (isset($params['personal_product_code'])) {
            $businessOptions['personal_product_code'] = $params['personal_product_code'];
        }
        // sign_scene string(64) 签约场景码，该值需要与系统/页面签约接口调用时传入的值保持一 致。如：周期扣款场景与调用 alipay.user.agreement.page.sign(支付宝个人协议页面签约接口) 签约时的 sign_scene 相同。 注意：当传入商户签约号 external_agreement_no 时，该值不能为空或默认值 DEFAULT|DEFAULT。
        if (isset($params['sign_scene'])) {
            $businessOptions['sign_scene'] = $params['sign_scene'];
        }
        // external_agreement_no string(32) 代扣协议中标示用户的唯一签约号(确保在商户系统中 唯一)。 格式规则:支持大写小写字母和数字，最长 32 位
        if (isset($params['external_agreement_no'])) {
            $businessOptions['external_agreement_no'] = $params['external_agreement_no'];
        }
        // third_party_type 可选 string(32) 签约第三方主体类型。对于三方协议，表示当前用户和哪一类的第三方主体进行签约。 默认为PARTNER。
        if (isset($params['third_party_type'])) {
            $businessOptions['third_party_type'] = $params['third_party_type'];
        }
        // agreement_no 可选 string(64) 支付宝系统中用以唯一标识用户签约记录的编号（用户签约成功后的协议号 ） ，如果传了该参数，其他参数会被忽略
        if (isset($params['agreement_no'])) {
            $businessOptions['agreement_no'] = $params['agreement_no'];
        }
        $method = 'alipay.user.agreement.query';
        $response = $this->instance::util()->generic()->execute($method, [], $businessOptions);
        return $this->createResponse($method, $response);
    }

    /**
     * 支付宝-个人代扣协议解约
     * @link https://opendocs.alipay.com/open/b841da1f_alipay.user.agreement.unsign
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function agreementUnsign(array $params)
    {
        $businessOptions = [];
        // alipay_user_id string(32) 用户的支付宝账号对应 的支付宝唯一用户号，以 2088 开头的 16 位纯数字 组成。 本参数与alipay_logon_id若都填写，则以本参数为准，优先级高于 alipay_logon_id
        if (isset($params['alipay_user_id'])) {
            $businessOptions['alipay_user_id'] = $params['alipay_user_id'];
        }
        // alipay_open_id string(128) 用户的支付宝账号对应 的支付宝唯一用户号， 本参数与alipay_logon_id若都填写，则以本参数为准，优先级高于 alipay_logon_id
        if (isset($params['alipay_open_id'])) {
            $businessOptions['alipay_open_id'] = $params['alipay_open_id'];
        }
        // alipay_logon_id string(100) 用户的支付宝登录账号，支持邮箱或手机号码格式。本参数与alipay_open_id 或 alipay_user_id 同时填写，优先按照 alipay_open_id 或 alipay_user_id 处理。
        if (isset($params['alipay_logon_id'])) {
            $businessOptions['alipay_logon_id'] = $params['alipay_logon_id'];
        }
        // external_agreement_no string(32) 代扣协议中标示用户的唯一签约号(确保在商户系统中 唯一)。 格式规则:支持大写小写字母和数字，最长 32 位
        if (isset($params['external_agreement_no'])) {
            $businessOptions['external_agreement_no'] = $params['external_agreement_no'];
        }
        // agreement_no 可选 string(64) 支付宝系统中用以唯一标识用户签约记录的编号（用户签约成功后的协议号 ） ，如果传了该参数，其他参数会被忽略
        if (isset($params['agreement_no'])) {
            $businessOptions['agreement_no'] = $params['agreement_no'];
        }
        // personal_product_code string(64) 个人签约产品码，商户和支付宝签约时确定。 示例值 GENERAL_WITHHOLDING
        if (isset($params['personal_product_code'])) {
            $businessOptions['personal_product_code'] = $params['personal_product_code'];
        }
        // sign_scene string(64) 签约场景码，该值需要与系统/页面签约接口调用时传入的值保持一 致。如：周期扣款场景与调用 alipay.user.agreement.page.sign(支付宝个人协议页面签约接口) 签约时的 sign_scene 相同。 注意：当传入商户签约号 external_agreement_no 时，该值不能为空或默认值 DEFAULT|DEFAULT。
        if (isset($params['sign_scene'])) {
            $businessOptions['sign_scene'] = $params['sign_scene'];
        }
        // third_party_type 可选 string(32) 签约第三方主体类型。对于三方协议，表示当前用户和哪一类的第三方主体进行签约。 默认为PARTNER。
        if (isset($params['third_party_type'])) {
            $businessOptions['third_party_type'] = $params['third_party_type'];
        }
        // extend_params 扩展参数
        if (isset($params['extend_params'])) {
            $businessOptions['extend_params'] = $params['extend_params'];
        }
        // operate_type string(10) 操作类型:confirm（解约确认），invalid（解约作废）
        if (isset($params['operate_type'])) {
            $businessOptions['operate_type'] = $params['operate_type'];
        }
        $method = 'alipay.user.agreement.unsign';
        $response = $this->instance::util()->generic()->execute($method, [], $businessOptions);
        return $this->createResponse($method, $response);
    }

    /**
     * 支付宝-周期性扣款协议执行计划修改
     * @link https://opendocs.alipay.com/open/ed428330_alipay.user.agreement.executionplan.modify
     * @param $agreementNo
     * @param $deductTime
     * @param string $memo
     * @return Response
     * @throws \Exception
     */
    public function agreementExecutionplanModify($agreementNo, $deductTime, $memo = '')
    {
        $businessOptions = [
            'agreement_no' => $agreementNo,
            'deduct_time' => $deductTime,
            'memo' => $memo,
        ];
        $method = 'alipay.user.agreement.executionplan.modify';
        $response = $this->instance::util()->generic()->execute($method, [], $businessOptions);
        return $this->createResponse($method, $response);
    }

    /**
     * 申请退款
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function refund(array $params)
    {
        // refund_amount 必选 price (16) 【描述】需要退款的金额，该金额不能大于订单金额,单位为元，支持两位小数
        if (!isset($params['refund_amount']) || !is_numeric($params['refund_amount'])) {
            throw new \InvalidArgumentException('缺省退款金额');
        }
        // out_trade_no 和 trade_no 二选一
        if (!isset($params['out_trade_no']) && !isset($params['trade_no'])) {
            throw new \InvalidArgumentException('商户订单号和支付宝交易号不能同时为空');
        }
        $businessOptions = [];
        // refund_reason 可选 string(256) 【描述】退款的原因说明
        if (isset($params['refund_reason'])) {
            $businessOptions['refund_reason'] = trim($params['refund_reason']);
        }
        // out_request_no 可选 string(64) 【描述】退款请求号。 标识一次退款请求，需要保证在交易号下唯一，如需部分退款，则此参数必传。 注：针对同一次退款请求，如果调用接口失败或异常了，重试时需要保证退款请求号不能变更，防止该笔交易重复退款。支付宝会保证同样的退款请求号多次请求只会退一次。
        if (isset($params['out_request_no'])) {
            $businessOptions['out_request_no'] = trim($params['out_request_no']);
        }
        // refund_goods_detail 可选RefundGoodsDetail[] 退款包含的商品列表信息
        if (isset($params['refund_goods_detail']) && is_array($params['refund_goods_detail'])) {
            $businessOptions['refund_goods_detail'] = $params['refund_goods_detail'];
        }
        /**
         * refund_royalty_parameters 可选 OpenApiRoyaltyDetailInfoPojo[]
         * 【描述】退分账明细信息。 注：
         * 1.当面付且非直付通模式无需传入退分账明细，系统自动按退款金额与订单金额的比率，从收款方和分账收入方退款，不支持指定退款金额与退款方。
         * 2.直付通模式，电脑网站支付，手机 APP 支付，手机网站支付产品，须在退款请求中明确是否退分账，从哪个分账收入方退，退多少分账金额；如不明确，默认从收款方退款，收款方余额不足退款失败。不支持系统按比率退款。
         */
        if (isset($params['refund_royalty_parameters']) && is_array($params['refund_royalty_parameters'])) {
            $businessOptions['refund_royalty_parameters'] = $params['refund_royalty_parameters'];
        }
        /**
         *  query_options 可选string[](1024)
         * 【描述】查询选项。 商户通过上送该参数来定制同步需要额外返回的信息字段，数组格式。
         * 【枚举值】
         * 本次退款使用的资金渠道: refund_detail_item_list
         * 银行卡冲退信息: deposit_back_info
         * 本次退款退的券信息: refund_voucher_detail_list
         * 【示例值】["refund_detail_item_list"]
         */
        if (isset($params['query_options'])) {
            $businessOptions['query_options'] = $params['query_options'];
        }
        // related_settle_confirm_no 可选 string(64) 【描述】针对账期交易，在确认结算后退款的话，需要指定确认结算时的结算单号。
        if (isset($params['related_settle_confirm_no'])) {
            $businessOptions['related_settle_confirm_no'] = $params['related_settle_confirm_no'];
        }
        $payment = $this->instance::payment()->common();
        if (!empty($businessOptions)) {
            $payment = $payment->batchOptional($businessOptions);
        }
        $response = $payment->refund($params['out_trade_no'], $params['refund_amount']);
        return $this->createResponse('alipay.trade.refund', $response);
    }

    /**
     * 退款查询
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function refundQuery(array $params)
    {
        // out_request_no 必选 string(64) 】退款请求号。 请求退款接口时，传入的退款请求号，如果在退款请求时未传入，则该值为创建交易时的商户订单号。
        if (!isset($params['out_request_no'])) {
            throw new \InvalidArgumentException('缺省退款请求号');
        }
        // out_trade_no 和 trade_no 二选一
        if (!isset($params['out_trade_no']) && !isset($params['trade_no'])) {
            throw new \InvalidArgumentException('商户订单号和支付宝交易号不能同时为空');
        }
        $businessOptions = [];
        if (isset($params['trade_no'])) {
            $businessOptions['trade_no'] = trim($params['trade_no']);
        }
        // org_pid 可选 string(16) 【描述】银行间联模式下有用，其它场景请不要使用；
        if (isset($params['org_pid'])) {
            $businessOptions['org_pid'] = $params['org_pid'];
        }
        // query_options 可选 string(32) 【描述】查询选项，商户通过上送该参数来定制查询返回信息
        if (isset($params['query_options'])) {
            $businessOptions['query_options'] = $params['query_options'];
        }
        $payment = $this->instance::payment()->common();
        if (!empty($businessOptions)) {
            $payment = $payment->batchOptional($businessOptions);
        }
        $outTradeNo = $params['out_trade_no'] ?? '';
        $outRequestNo = $params['out_request_no'];
        $response = $payment->queryRefund($outTradeNo, $outRequestNo);
        return $this->createResponse('alipay.trade.fastpay.refund.query', $response);
    }

    /**
     * 支付宝-收单退款冲退完成通知
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function refundDepositbackCompleted(array $params)
    {
        $businessOptions = [];
        // trade_no 必选 string(64) 支付宝交易号
        if (!isset($params['trade_no'])) {
            throw new \InvalidArgumentException('缺省支付宝交易号');
        }
        $businessOptions['trade_no'] = $params['trade_no'];
        // out_request_no 必选 string(64) 商户订单号
        if (!isset($params['out_trade_no'])) {
            throw new \InvalidArgumentException('缺省商户订单号');
        }
        $businessOptions['out_trade_no'] = $params['out_trade_no'];
        // out_request_no 必选 string(64) 退款请求号
        if (!isset($params['out_request_no'])) {
            throw new \InvalidArgumentException('缺省退款请求号');
        }
        $businessOptions['out_request_no'] = $params['out_request_no'];
        // dback_status 必选 string(8) 银行卡冲退状态。S-成功，F-失败。银行卡冲退失败，资金自动转入用户支付宝余额。
        if (!isset($params['dback_status'])) {
            throw new \InvalidArgumentException('缺省退款请求号');
        }
        $businessOptions['dback_status'] = $params['dback_status'];
        // dback_amount 必选 string(9) 银行卡冲退金额，仅当dback_status=S时，才会返回。单位：元。
        if (!isset($params['dback_amount'])) {
            throw new \InvalidArgumentException('缺省银行卡冲退金额');
        }
        $businessOptions['dback_amount'] = $params['dback_amount'];
        // bank_ack_time 可选 string(32) 银行卡冲退成功时间，银行响应时间，格式为yyyy-MM-dd HH:mm:ss
        if (isset($params['bank_ack_time'])) {
            if (!isValidDateTime($params['bank_ack_time'])) {
                throw new \InvalidArgumentException('银行卡冲退成功时间格式错误');
            }
            $businessOptions['bank_ack_time'] = $params['bank_ack_time'];
        }
        // est_bank_receipt_time 可选 string(32) 银行卡冲退预计到账时间，格式为yyyy-MM-dd HH:mm:ss
        if (isset($params['est_bank_receipt_time'])) {
            if (!isValidDateTime($params['est_bank_receipt_time'])) {
                throw new \InvalidArgumentException('银行卡冲退预计到账时间格式错误');
            }
            $businessOptions['est_bank_receipt_time'] = $params['est_bank_receipt_time'];
        }
        $bizParams = [];
        $method = 'alipay.trade.refund.depositback.completed';
        $response = $this->instance::util()->generic()->execute($method, $businessOptions, $bizParams);
        return $this->createResponse($method, $response);

    }

    /**
     * 支付宝-统一收单交易撤销接口
     * @link https://opendocs.alipay.com/open/13399511_alipay.trade.cancel
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function cancel(array $params)
    {
        // out_trade_no 和 trade_no 二选一
        if (!isset($params['out_trade_no']) && !isset($params['trade_no'])) {
            throw new \InvalidArgumentException('商户订单号和支付宝交易号不能同时为空');
        }
        $businessOptions = [];
        // out_trade_no 原支付请求的商户订单号,和支付宝交易号不能同时为空
        if (isset($params['out_trade_no'])) {
            $businessOptions['out_trade_no'] = trim($params['out_trade_no']);
        }
        if (isset($params['trade_no'])) {
            $businessOptions['trade_no'] = trim($params['trade_no']);
        }
        $payment = $this->instance::payment()->common();
        if (!empty($businessOptions)) {
            $payment = $payment->batchOptional($businessOptions);
        }
        $outTradeNo = $params['out_trade_no'] ?? '';
        $response = $payment->cancel($outTradeNo);
        return $this->createResponse('alipay.trade.cancel', $response);
    }

    /**
     * 支付宝-申请交易账单
     * @link https://opendocs.alipay.com/open/e81ed5f1_alipay.data.dataservice.bill.downloadurl.query
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function downloadBill(array $params)
    {
        /**
         * bill_type 必选 string(20)
         * 【枚举值】
         * 商户基于支付宝交易收单的业务账单: trade
         * 基于商户支付宝余额收入及支出等资金变动的账务账单: signcustomer
         * 营销活动账单，包含营销活动的发放，核销记录: merchant_act
         * 直付通二级商户查询交易的业务账单: trade_zft_merchant
         * 直付通平台商查询二级商户流水使用，返回所有二级商户流水。: zft_acc
         * 每日结算到卡的资金对应的明细，下载内容包含批次结算到卡明细文件（示例）和批次结算到卡汇总文件（示例）；若查询时间范围内有多个批次，会将多个批次的明细和汇总文件打包到一份压缩包中；: settlementMerge
         */
        if (!isset($params['bill_type'])) {
            throw new \InvalidArgumentException('商户订单号和支付宝交易号不能同时为空');
        }

        /**
         * bill_date 必选 string(15)
         * 账单时间： * 日账单格式为yyyy-MM-dd，最早可下载2016年1月1日开始的日账单。不支持下载当日账单，只能下载前一日24点前的账单数据（T+1），当日数据一般于次日 9 点前生成，特殊情况可能延迟。 * 月账单格式为yyyy-MM，最早可下载2016年1月开始的月账单。不支持下载当月账单，只能下载上一月账单数据，当月账单一般在次月 3 日生成，特殊情况可能延迟。 * 当biz_type为settlementMerge时候，时间为汇总批次结算资金到账的日期，日期格式为yyyy-MM-dd，最早可下载2023年4月17日及以后的账单。
         * 【示例值】2016-04-05
         */
        if (!isset($params['bill_date'])) {
            throw new \InvalidArgumentException('账单时间不能为空');
        }

        if (!isValidDateTime($params['bill_date'], 'Y-m-d')) {
            throw new \InvalidArgumentException('账单时间格式错误');
        }

        $businessOptions = [];
        // smid 可选 二级商户smid，这个参数只在bill_type是trade_zft_merchant时才能使用
        if (isset($params['smid'])) {
            $businessOptions['smid'] = $params['smid'];
        }
        $payment = $this->instance::payment()->common();
        if (!empty($businessOptions)) {
            $payment = $payment->batchOptional($businessOptions);
        }
        $billType = $params['bill_type'] ?? 'trade';
        $billDate = $params['bill_date'] ?? '';
        $response = $payment->downloadBill($billType, $billDate);
        return $this->createResponse('alipay.data.dataservice.bill.downloadurl.query', $response);
    }


    /**
     * 支付宝-交易关闭接口
     * @link https://opendocs.alipay.com/open/e84f0d79_alipay.trade.close
     * @param array $params
     * @return Response
     * @throws \Exception
     */
    public function close(array $params)
    {
        // out_trade_no 和 trade_no 二选一
        if (!isset($params['out_trade_no']) && !isset($params['trade_no'])) {
            throw new \InvalidArgumentException('商户订单号和支付宝交易号不能同时为空');
        }
        $businessOptions = [];
        // trade_no 可选 该交易在支付宝系统中的交易流水号。最短 16 位，最长 64 位。和out_trade_no不能同时为空，如果同时传了 out_trade_no和 trade_no，则以 trade_no为准
        if (isset($params['trade_no'])) {
            $businessOptions['trade_no'] = trim($params['trade_no']);
        }
        // operator_id 可选 string(28)【描述】商户操作员编号
        if (isset($params['operator_id'])) {
            $businessOptions['operator_id'] = trim($params['operator_id']);
        }
        $payment = $this->instance::payment()->common();
        if (!empty($businessOptions)) {
            $payment = $payment->batchOptional($businessOptions);
        }
        $outTradeNo = $params['out_trade_no'] ?? '';
        $response = $payment->close($outTradeNo);
        return $this->createResponse('alipay.trade.close', $response);
    }

    /**
     * 检查签约数据
     * @param array $agreementSignTmpParams
     * @return array
     */
    public function checkAgreementOptions(array $agreementSignTmpParams)
    {
        $agreementSignParams = [];
        // personal_product_code 必选  个人签约产品码，商户和支付宝签约时确定，商户可咨询技术支持。
        $personalProductCode = $agreementSignTmpParams['personal_product_code'] ?? $this->personalProductCode;
        if (empty($personalProductCode)) {
            throw new \InvalidArgumentException('请配置支付宝个人签约产品码');
        }
        $agreementSignParams['personal_product_code'] = $personalProductCode;
        if (empty($this->signScene)) {
            throw new \InvalidArgumentException('请配置支付宝协议签约场景');
        }
        $agreementSignParams['sign_scene'] = $this->signScene;
        if (!isset($agreementSignTmpParams['access_params']['channel'])) {
            throw new \InvalidArgumentException('请设置支付宝签约渠道');
        }
        if (!in_array($agreementSignTmpParams['access_params']['channel'], [self::ALIPAYAPP_CHANNEL, self::QRCODE_CHANNEL, self::QRCODEORSMS_CHANNEL])) {
            throw new \InvalidArgumentException('设置的支付宝签约渠道类型错误');
        }
        $agreementSignParams['access_params'] = [
            'channel' => $agreementSignTmpParams['access_params']['channel']
        ];
        // period_rule_params
        $periodRuleParams = [];
        $periodRuleTmpParams = $agreementSignTmpParams['period_rule_params'] ?? [];
        if (empty($periodRuleTmpParams)) {
            throw new \InvalidArgumentException('请配置支付宝签约周期规则参数');
        }
        // period_rule_params period_type 周期数period是周期扣款产品必填。与另一参数period_type组合使用确定扣款周期，例如period_type为DAY，period=90，则扣款周期为90天。
        if (!isset($periodRuleTmpParams['period_type'])) {
            throw new \InvalidArgumentException('请配置支付宝签约周期规则参数类型');
        }
        if (!in_array($periodRuleTmpParams['period_type'], [self::PERIOD_TYPE_DAY, self::PERIOD_TYPE_MONTH])) {
            throw new \InvalidArgumentException('设置的支付宝签约周期规则参数类型错误');
        }
        // 周期数 period 不允许小于 7（可以等于 7）
        if ($periodRuleTmpParams['period_type'] == self::PERIOD_TYPE_DAY && $periodRuleTmpParams['period'] < 7) {
            throw new \InvalidArgumentException('周期数 period 不允许小于 7');
        }
        $periodRuleParams['period_type'] = $periodRuleTmpParams['period_type'];
        // period_rule_params period 【描述】周期数period是周期扣款产品必填。与另一参数period_type组合使用确定扣款周期，例如period_type为DAY，period=90，则扣款周期为90天。
        if (!isset($periodRuleTmpParams['period'])) {
            throw new \InvalidArgumentException('请配置支付宝签约周期规则参数周期数');
        }
        if (!is_numeric($periodRuleTmpParams['period']) || $periodRuleTmpParams['period'] <= 0) {
            throw new \InvalidArgumentException('支付宝签约周期规则参数周期数错误');
        }
        $periodRuleParams['period'] = $periodRuleTmpParams['period'];
        // period_rule_params execute_time 【描述】首次执行时间execute_time是周期扣款产品必填，即商户发起首次扣款的时间。精确到日，格式为yyyy-MM-dd
        if (!isset($periodRuleTmpParams['execute_time'])) {
            throw new \InvalidArgumentException('请设置支付宝签约首次执行时间');
        }
        if (!isValidDateTime($periodRuleTmpParams['execute_time'], 'Y-m-d')) {
            throw new \InvalidArgumentException('支付宝签约首次执行时间格式错误');
        }
        // 周期类型使用 MONTH 的时候，计划扣款时间 execute_time 不允许传 28 日之后的日期（可以传 28 日）
        $signDay = date('d', strtotime($periodRuleTmpParams['execute_time']));
        if ($periodRuleTmpParams['period_type'] == self::PERIOD_TYPE_MONTH && $signDay > 28) {
            throw new \InvalidArgumentException('支付宝签约时间execute_time不允许传28日之后的日期');
        }
        $periodRuleParams['execute_time'] = $periodRuleTmpParams['execute_time'];
        // period_rule_params single_amount 【描述】单次扣款最大金额single_amount是周期扣款产品必填，即每次发起扣款时限制的最大金额，单位为元
        if (!isset($periodRuleTmpParams['single_amount'])) {
            throw new \InvalidArgumentException('请设置支付宝签单次扣款最大金额');
        }
        if ($periodRuleTmpParams['single_amount'] <= 0 || !is_numeric($periodRuleTmpParams['single_amount'])) {
            throw new \InvalidArgumentException('支付宝签单次扣款最大金额为数字且大于0');
        }
        $periodRuleParams['single_amount'] = $periodRuleTmpParams['single_amount'];
        // period_rule_params total_amount 【描述】总金额限制，单位为元-可选
        if (isset($periodRuleTmpParams['total_amount'])) {
            if ($periodRuleTmpParams['total_amount'] <= 0 || !is_numeric($periodRuleTmpParams['total_amount'])) {
                throw new \InvalidArgumentException('支付宝签约总金额为数字且大于0');
            }
            $periodRuleParams['total_amount'] = $periodRuleTmpParams['total_amount'];
        }
        // period_rule_params total_payments 【描述】总扣款次数-可选。如果传入此参数，则商户成功扣款的次数不能超过此次数限制（扣款失败不计入）。
        if (isset($periodRuleTmpParams['total_payments'])) {
            if ($periodRuleTmpParams['total_payments'] <= 0 || !is_numeric($periodRuleTmpParams['total_payments'])) {
                throw new \InvalidArgumentException('支付宝签约总扣款次数为数字且大于0');
            }
            $periodRuleParams['total_payments'] = $periodRuleTmpParams['total_payments'];
        }
        $agreementSignParams['period_rule_params'] = $periodRuleParams;
        // effect_time 签约请求有效时间-可选。设置签约请求的有效时间，单位为秒。如传入600，商户发起签约请求到用户进入支付宝签约页面的时间差不能超过10分钟。
        if (isset($agreementSignTmpParams['effect_time'])) {
            if ($agreementSignTmpParams['effect_time'] <= 0 || !is_numeric($agreementSignTmpParams['effect_time'])) {
                throw new \InvalidArgumentException('支付宝签约请求有效时间为数字且大于0');
            }
            $agreementSignParams['effect_time'] = $agreementSignTmpParams['effect_time'];
        }
        // external_agreement_no 【描述】商户签约号，代扣协议中标示用户的唯一签约号（确保在商户系统中唯一）-可选。 格式规则：支持大写小写字母和数字，最长32位。 商户系统按需传入，如果同一用户在同一产品码、同一签约场景下，签订了多份代扣协议，那么需要指定并传入该值
        if (isset($agreementSignTmpParams['external_agreement_no'])) {
            // 格式规则：支持大写小写字母和数字，最长32位
            if (!preg_match('/^[0-9a-zA-Z]{1,32}$/', $agreementSignTmpParams['external_agreement_no'])) {
                throw new \InvalidArgumentException('支付宝签约商户签约号格式错误');
            }
            $agreementSignParams['external_agreement_no'] = $agreementSignTmpParams['external_agreement_no'];
        }
        return $agreementSignParams;
    }

    /**
     * 检查支付参数goods_detail 必要参与
     * @param array $tmpGoodsDetail
     * @return array
     */
    protected function checkGoodsDetail(array $tmpGoodsDetail)
    {
        $goodsDetail = [];
        // goods_id 必选 string(64) 描述】商品的编号，该参数传入支付券上绑定商品goods_id, 倘若无支付券需要消费，该字段传入商品最小粒度的商品ID（如：若商品有sku粒度，则传商户sku粒度的ID）
        if (!isset($tmpGoodsDetail['goods_id'])) {
            throw new \InvalidArgumentException('商品的编号不能为空');
        }
        $goodsDetail['goods_id'] = $tmpGoodsDetail['goods_id'];
        // goods_name 必选 string(256) 商品的实际名称
        if (!isset($tmpGoodsDetail['goods_name'])) {
            throw new \InvalidArgumentException('商品的实际名称不能为空');
        }
        $goodsDetail['goods_name'] = $tmpGoodsDetail['goods_name'];
        // quantity 必选 int 商品数量
        if (!isset($tmpGoodsDetail['quantity'])) {
            throw new \InvalidArgumentException('商品数量不能为空');
        }
        if (!is_numeric($tmpGoodsDetail['quantity']) || $tmpGoodsDetail['quantity'] <= 0) {
            throw new \InvalidArgumentException('商品数量必须为大于0的整数');
        }
        $goodsDetail['quantity'] = $tmpGoodsDetail['quantity'];
        // price 必选 商品单价，单位为元
        if (!isset($tmpGoodsDetail['price'])) {
            throw new \InvalidArgumentException('商品单价不能为空');
        }
        if (!is_numeric($tmpGoodsDetail['price']) || $tmpGoodsDetail['price'] <= 0) {
            throw new \InvalidArgumentException('商品单价必须为大于0的数字');
        }
        // alipay_goods_id 可选string(32)【描述】支付宝定义的统一商品编号
        if (isset($tmpGoodsDetail['alipay_goods_id'])) {
            $goodsDetail['alipay_goods_id'] = $tmpGoodsDetail['alipay_goods_id'];
        }
        // goods_category 可选 string(24) 商品类目
        if (isset($tmpGoodsDetail['goods_category'])) {
            $goodsDetail['goods_category'] = $tmpGoodsDetail['goods_category'];
        }
        // categories_tree 可选 string(128) 商品类目树
        if (isset($tmpGoodsDetail['categories_tree'])) {
            $goodsDetail['categories_tree'] = $tmpGoodsDetail['categories_tree'];
        }
        // body 可选 string(1000) 商品描述信息
        if (isset($tmpGoodsDetail['body'])) {
            $goodsDetail['body'] = $tmpGoodsDetail['body'];
        }
        // show_url 可选 string(400) 商品的展示地址
        if (isset($tmpGoodsDetail['show_url'])) {
            $goodsDetail['show_url'] = $tmpGoodsDetail['show_url'];
        }
        // out_item_id 可选 string(100) 商家侧小程序商品ID，指商家提报给小程序商品库的商品。当前接口的extend_params.trade_component_order_id字段不为空时该字段必填，且与交易组件订单参数保持一致。了解小程序商品请参考：https://opendocs.alipay.com/mini/06uila?pathHash=63b6fba7
        if (isset($tmpGoodsDetail['out_item_id'])) {
            $goodsDetail['out_item_id'] = $tmpGoodsDetail['out_item_id'];
        }
        // out_sku_id 可选 string(64) 【描述】商家侧小程序商品ID，指商家提报给小程序商品库的商品。当前接口的extend_params.trade_component_order_id字段不为空时该字段必填，且与交易组件订单参数保持一致。了解小程序商品请参考：https://opendocs.alipay.com/mini/06uila?pathHash=63b6fba7
        if (isset($tmpGoodsDetail['out_sku_id'])) {
            $goodsDetail['out_sku_id'] = $tmpGoodsDetail['out_sku_id'];
        }
        return $goodsDetail;
    }

    /**
     * @param $subject
     * @param $outTradeNo
     * @param $totalAmount
     * @param array $businessOptions
     * @return string
     */
    protected function appPayExecute($subject, $outTradeNo, $totalAmount, array $businessOptions): string
    {
        $paymentApp = $this->instance::payment()->app();
        // 如果配置设置：notify_url，当前参数设置 notify_url 不会生效～
        if (isset($businessOptions['notify_url']) && (false !== filter_var($businessOptions['notify_url'], FILTER_VALIDATE_URL))) {
            $paymentApp = $paymentApp->asyncNotify($businessOptions['notify_url']);
            unset($businessOptions['notify_url']);
        }
        if (!empty($businessOptions)) {
            $paymentApp = $paymentApp->batchOptional($businessOptions);
        }
        return $paymentApp->pay($subject, $outTradeNo, $totalAmount)->body;
    }

    /**
     * @param INotify $notifyCallback
     * @return void
     */
    public function notify(INotify $notifyCallback)
    {
        $notifyData = $this->getNotifyData();
        if (empty($notifyData)) {
            throw new \InvalidArgumentException('异步通知数据为空');
        }
        //  notify_type
        if (!isset($notifyData['notify_type'])) {
            throw new \InvalidArgumentException('异步通知类型为空');
        }
        // sign
        if (!isset($notifyData['sign'])) {
            throw new \InvalidArgumentException('异步通知签名为空');
        }
        // 支付宝 notify_type 类型转化为 NOTIFIY_PAY NOTIFY_REFUND NOTIFY_BATCH_REFUND
        $notifyType = $this->getNotifyType($notifyData['notify_type']);
        //  校验 app_id
        if (!isset($notifyData['app_id']) || $notifyData['app_id'] != $this->appId) {
            throw new \InvalidArgumentException('异步通知app_id校验失败');
        }
        if (false === $this->instance::payment()->common()->verifyNotify($notifyData)) {
            throw new \InvalidArgumentException('异步通知签名校验失败');
        }
        $classNotifyType = $notifyCallback->getNotifyType();
        if ($classNotifyType == $notifyType) {
            $result = $notifyCallback->handle(self::SP_NAME, $notifyData);
            exit($this->notifyResponse($result));
        }
        throw new \InvalidArgumentException('异步通知类型与回调处理类型不匹配');
    }

    /**
     * @param string $method
     * @param Model $response
     * @return Response
     */
    protected function createResponse(string $method, Model $response): Response
    {
        $responseName = str_replace('.', '_', $method) . '_response';
        $data = null;
        $status = Response::STATUS_ERROR;
        if ($response->code == '10000') {
            $status = Response::STATUS_SUCCESS;
            $responseData = json_decode($response->httpBody, true);
            if (isset($responseData[$responseName])) {
                $data = $responseData[$responseName];
            }
        }
        return new Response($status, $response->code, $response->msg, $data, $response->subCode, $response->subMsg);
    }

    /**
     * 获取异步通知数据
     * @return array
     */
    public function getNotifyData(): array
    {
        return empty($_POST) ? $_GET : $_POST;
    }

    /**
     * @param $notifyType
     * @return string
     */
    protected function getNotifyType($notifyType): string
    {
        switch ($notifyType) {
            case 'trade_status_sync':
                return INotify::NOTIFY_PAY;
            case 'dut_user_sign':
                return INotify::NOTIFY_SIGN;
            case 'dut_user_unsign':
                return INotify::NOTIFY_UNSIGN;
            case 'servicemarket_order_notify':
                return INotify::NOTIFY_SERVICE_MARKET_ORDER;
            case 'open_app_auth_notify':
                return INotify::NOTIFY_OPEN_APP_AUTH;
            default:
                throw new \InvalidArgumentException('支付宝异步通知类型未知');
        }
    }

    /**
     * @param $result
     * @return string
     */
    protected function notifyResponse($result)
    {
        if ($result) {
            return 'success';
        }
        return 'fail';
    }
}