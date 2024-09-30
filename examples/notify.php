<?php
/**
 * 异步通知示例
 * @file examples/pay.php
 */
use Simplephp\PaymentSdk\Abstracts\APayNotify;
use Simplephp\PaymentSdk\Abstracts\ARefundNotify;
use Simplephp\PaymentSdk\Abstracts\ASignNotify;
use Simplephp\PaymentSdk\Abstracts\AUnSignNotify;
use Simplephp\PaymentSdk\Payment;
use Simplephp\PaymentSdk\Provider\Alipay;

require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('Asia/Shanghai');

$config = [
    'alipay' => [
        'default' => [
            // 支付宝-应用ID-必填
            'app_id' => '2021**1256',
            // 支付宝-应用私钥-必填
            'app_private_key' => 'MIIE***cyQw==',
            // 支付宝-支付宝公钥-选填（alipay_public_key 和 （alipay_public_cert_path、alipay_root_cert_path、app_public_cert_path）二选一
            'alipay_public_key' => 'MIIBI***AQAB',
            // 支付宝-支付宝公钥证书路径-选填
            'alipay_public_cert_path' => '',
            // 支付宝-支付宝根证书路径-选填
            'alipay_root_cert_path' => __DIR__.'/cert/alipayRootCert.crt',
            // 支付宝-应用公钥证书文件路径-选填
            'app_public_cert_path' =>  __DIR__.'/cert/appCertPublicKey_2016082000295641.crt',
            // 支付宝-同步通知地址-选填
            'return_url' => 'https://www.nineton.cn/alipay/return',
            // 支付宝-异步通知地址-选填
            'notify_url' => 'https://www.nineton.cn/alipay/notify',
            // 支付宝-周期扣款/商家扣款-选填 product_code：产品码 周期扣款：CYCLE_PAY_AUTH：商家扣款：GENERAL_WITHHOLDING
            'product_code' => Alipay::GENERAL_WITHHOLDING,
            // 支付宝-周期扣款/商家扣款-选填 sign_scene：签约场景码，具体参数请商家完成产品签约后，根据业务场景或用户购买商品的差异性对应新增模版及场景码。 说明：登录 商家平台 > 产品大全 > 商家扣款 > 功能管理 > 修改 > 设置模版 可新增模版及场景码。商家在确认新增模版及场景码完成后，签约接入时需要传入模版中实际填写的场景码。场景码格式详情可查看
            'sign_scene' => 'INDUSTRY|DEFAULT_SCENE',
            // 支付宝-周期扣款/商家扣款-选填 签约个人产品码
            'personal_product_code' => 'CYCLE_PAY_AUTH_P',
            //'mode' => Pay::MODE_NORMAL,
        ]
    ],
    'wechat' => [
        'default' => [
            // 微信-商户号-必填
            'mch_id' => '151***131',
            // 微信-应用ID-必填
            'app_id' => 'wxd***2336',
            // 微信-v3商户秘钥-必填
            'api_v3_key' => 'RIGK***ZjZ3',
            // 微信-商户私钥文件地址-必填
            'merchant_private_key_file_path' => __DIR__ . '/cert/apiclient_key.pem',
            // 微信-商户API证书序列号-必填
            'merchant_certificate_serial' => '6645***D0744',
            // 微信-支付平台证书地址-必填，使用 composer 生成
            // composer exec CertificateDownloader.php -- -k ${apiV3key} -m ${mchId} -f ${mchPrivateKeyFilePath} -s ${mchSerialNo} -o ${outputFilePath}
            'platform_certificate_file_path' => __DIR__ . '/cert/wechatpay_61A54B44E797D26EAD2B47466985513AEE09F60C.pem',
            // 支付宝-同步通知地址-选填
            'return_url' => 'https://www.nineton.cn/alipay/return',
            // 支付宝-异步通知地址-选填
            'notify_url' => 'https://www.nineton.cn/alipay/notify',
            // 选填-默认为正常模式。可选为： MODE_NORMAL, MODE_SERVICE
            //'mode' => Pay::MODE_NORMAL,
        ]
    ],
];

/****************************************************
 * 支付宝-交易异步通知
 * @link https://opendocs.alipay.com/open/204/105301?pathHash=fef00e6d
 ****************************************************/
class TestPayNotify extends APayNotify
{
    /**
     * @param string $serviceProvider
     * @param array $notifyData
     * @return false
     */
    public function handle(string $serviceProvider, array $notifyData)
    {
        /**
         * notifyType 为 pay 时候，notifyData['trade_status'] 为交易状态，一般只会处理 TRADE_SUCCESS
         * 状态说明
         * 枚举名称    枚举说明
         * WAIT_BUYER_PAY    交易创建，等待买家付款。
         * TRADE_CLOSED    未付款交易超时关闭，或支付完成后全额退款。
         * TRADE_SUCCESS    交易支付成功。
         * TRADE_FINISHED    交易结束，不可退款。
         */

        /**
         * 通知触发条件
         * 触发条件名    触发条件描述    触发条件默认值
         * TRADE_FINISHED    交易完成    true（触发通知）
         * TRADE_SUCCESS    支付成功    true（触发通知）
         * WAIT_BUYER_PAY    交易创建    false（不触发通知）
         * TRADE_CLOSED    交易关闭    true（触发通知）
         */
        /**
         * notify_type = trade_status_sync 支付通知数据样例
         * trade_status = TRADE_SUCCESS 通知数据样例
         * [gmt_create] => 2024-09-29 00:00:13
         * [charset] => UTF-8
         * [seller_email] => ***@gmail.cn
         * [subject] => xxxx
         * [sign] => LunI***G1+A==
         * [buyer_id] => 2088***5463
         * [invoice_amount] => 9.90
         * [notify_id] => 2024***5007
         * [fund_bill_list] => [{"amount":"9.90","fundChannel":"ALIPAYACCOUNT"}]
         * [notify_type] => trade_status_sync
         * [trade_status] => TRADE_SUCCESS
         * [receipt_amount] => 9.90
         * [app_id] => 2021***9211
         * [buyer_pay_amount] => 9.90
         * [sign_type] => RSA2
         * [seller_id] => 2088***1320
         * [gmt_payment] => 2024-09-29 00:00:14
         * [notify_time] => 2024-09-29 00:00:15
         * [version] => 1.0
         * [out_trade_no] => 2024***0312
         * [total_amount] => 9.90
         * [trade_no] => 2024***3584
         * [auth_app_id] => 2021***9211
         * [buyer_logon_id] => 178****8191
         * [point_amount] => 0.00
         */
        return false;
    }
}

try {
    $alipay = Payment::config($config)->alipay();
    // 原始通知数据
    $notifyData = $alipay->getNotifyData();
    $alipay->notify(new TestPayNotify());
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage() . PHP_EOL;
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

/****************************************************
 * 支付宝-签约异步通知
 * @link https://opendocs.alipay.com/open/08ayiq?pathHash=a2d4e097
 ****************************************************/
class TestSignNotify extends ASignNotify
{
    /**
     * notify_type = dut_user_sign 签约通知数据样例
     * [charset] => UTF-8
     * [notify_time] => 2024-09-29 08:38:52
     * [alipay_user_id] => 2088****1401
     * [sign] => OqLAiM0QAp9v***==
     * [external_agreement_no] => 2024***7757
     * [version] => 1.0
     * [sign_time] => 2024-09-29 08:38:52
     * [notify_id] => 2024***2879
     * [notify_type] => dut_user_sign
     * [agreement_no] => 2024***5440
     * [invalid_time] => 2115-02-01 00:00:00
     * [auth_app_id] => 2021***9211
     * [personal_product_code] => CYCLE_PAY_AUTH_P
     * [valid_time] => 2024-09-29 08:38:52
     * [app_id] => 2021***9211
     * [next_deduct_time] => 2024-11-01
     * [sign_type] => RSA2
     * [sign_scene] => INDUSTRY|VOICEPAY
     * [status] => NORMAL
     * [alipay_logon_id] => 195******81
     */
    /**
     * @param string $serviceProvider
     * @param array $notifyData
     * @return void
     */
    public function handle(string $serviceProvider, array $notifyData)
    {

    }
}

try {
    $alipay = Payment::config($config)->alipay();
    // 原始通知数据
    $notifyData = $alipay->getNotifyData();
    $alipay->notify(new TestSignNotify());
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage() . PHP_EOL;
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

/****************************************************
 * 支付宝-解约异步通知
 * @link https://opendocs.alipay.com/open/08ayiq?pathHash=a2d4e097
 ****************************************************/
class TestUnSignNotify extends AUnSignNotify
{
    /**
     * notify_type = dut_user_sign 解约通知数据样例
    [charset] => UTF-8
    [notify_time] => 2024-09-29 01:37:40
    [unsign_time] => 2024-09-28 22:10:35
    [alipay_user_id] => 2088632060793691
    [sign] => ssPJ***gCRQ==
    [external_agreement_no] => 2024***2714
    [version] => 1.0
    [notify_id] => 2024***4728
    [notify_type] => dut_user_unsign
    [agreement_no] => 2024***7669
    [auth_app_id] => 2021***9211
    [personal_product_code] => CYCLE_PAY_AUTH_P
    [app_id] => 2021***9211
    [sign_type] => RSA2
    [alipay_logon_id] => 199******00
    [sign_scene] => INDUSTRY|VOICEPAY
    [status] => UNSIGN
     */
    /**
     * @param string $serviceProvider
     * @param array $notifyData
     * @return void
     */
    public function handle(string $serviceProvider, array $notifyData)
    {

    }
}

try {
    $alipay = Payment::config($config)->alipay();
    // 原始通知数据
    $notifyData = $alipay->getNotifyData();
    $alipay->notify(new TestUnSignNotify());
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage() . PHP_EOL;
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

/****************************************************
 * 微信-交易异步通知
 * @link https://pay.weixin.qq.com/docs/merchant/apis/in-app-payment/payment-notice.html
 ****************************************************/
class TestWechatPayNotify extends APayNotify
{
    /**
     * @param string $serviceProvider
     * @param array $notifyData
     * @return void
     */
    public function handle(string $serviceProvider, array $notifyData)
    {
        $transactionID = $notifyData['transaction_id'] ?? '';
        $outTradeNo = $notifyData['out_trade_no'] ?? '';
    }
}

try {
    $wechat = Payment::config($config)->wechat();
    $notifyData = $wechat->getNotifyData();
    $wechat->notify(new TestWechatPayNotify());
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage() . PHP_EOL;
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

/****************************************************
 * 微信-退款结果通知 [未测试]
 * @link https://pay.weixin.qq.com/docs/merchant/apis/in-app-payment/refund-result-notice.html
 ****************************************************/
class TestWechatRefundNotify extends ARefundNotify
{
    /**
     * @param string $serviceProvider
     * @param array $notifyData
     * @return void
     */
    public function handle(string $serviceProvider, array $notifyData)
    {

    }
}

try {
    $wechat = Payment::config($config)->wechat();
    $notifyData = $wechat->getNotifyData();
    $wechat->notify(new TestWechatRefundNotify());
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage() . PHP_EOL;
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}