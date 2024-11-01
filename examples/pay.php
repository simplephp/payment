<?php
/**
 * 支付示例
 * @file examples/pay.php
 */
use Simplephp\PaymentSdk\Payment;
use Simplephp\PaymentSdk\Provider\Alipay;
use Simplephp\PaymentSdk\Exception\PaymentException;

require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('Asia/Shanghai');

/**********************支付配置******************************/
$config = [
    'alipay' => [
        'default' => [
            // 支付宝-应用ID-必填
            'app_id' => '2021***408',
            // 支付宝-应用私钥-必填
            'merchant_private_key' => 'MIIE***m3I=',
            // 支付宝-支付宝公钥-选填（alipay_public_key【密钥模式】 和 （alipay_public_cert_path、alipay_root_cert_path、merchant_cert_path【证书模式】）二选一
            'alipay_public_key' => 'MIIB***AQAB',
            // 支付宝-支付宝公钥证书路径-选填
            'alipay_public_cert_path' => '',
            // 支付宝-支付宝根证书路径-选填
            'alipay_root_cert_path' => '',
            // 支付宝-应用公钥证书文件路径-选填
            'merchant_cert_path' => '',
            // 支付宝-同步通知地址-选填
            'return_url' => 'https://www.***.cn/alipay/return',
            // 支付宝-异步通知地址-选填
            'notify_url' => 'https://www.***.cn/alipay/notify',
            // 支付宝-周期扣款/商家扣款-选填 product_code：产品码 周期扣款：CYCLE_PAY_AUTH：商家扣款：GENERAL_WITHHOLDING
            'product_code' => Alipay::GENERAL_WITHHOLDING,
            // 支付宝-周期扣款/商家扣款-选填 sign_scene：签约场景码，具体参数请商家完成产品签约后，根据业务场景或用户购买商品的差异性对应新增模版及场景码。 说明：登录 商家平台 > 产品大全 > 商家扣款 > 功能管理 > 修改 > 设置模版 可新增模版及场景码。商家在确认新增模版及场景码完成后，签约接入时需要传入模版中实际填写的场景码。场景码格式详情可查看
            'sign_scene' => 'INDUSTRY|DEFAULT_SCENE',
            // 支付宝-周期扣款/商家扣款-选填 签约个人产品码
            'personal_product_code' => 'CYCLE_PAY_AUTH_P',
            //'mode' => Pay::MODE_NORMAL,
        ],
        'yy03' => [
            // 支付宝-应用ID-必填
            'app_id' => '2021*****408',
            // 支付宝-应用私钥-必填
            'merchant_private_key' => 'MIIE****m3I=',
            // 支付宝-支付宝公钥-选填（alipay_public_key 和 （alipay_public_cert_path、alipay_root_cert_path、merchant_cert_path）二选一
            'alipay_public_key' => 'MIIB***AQAB',
            // 支付宝-支付宝公钥证书路径-选填
            'alipay_public_cert_path' => __DIR__ . '/cert/alipayCertPublicKey_RSA2.crt',
            // 支付宝-支付宝根证书路径-选填
            'alipay_root_cert_path' => __DIR__ . '/cert/alipayRootCert.crt',
            // 支付宝-应用公钥证书文件路径-选填
            'merchant_cert_path' => __DIR__ . '/cert/appCertPublicKey_2019051064521003.crt',
            // 支付宝-同步通知地址-选填
            'return_url' => 'https://www.***.cn/alipay/return',
            // 支付宝-异步通知地址-选填
            'notify_url' => 'https://www.***.cn/alipay/notify',
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
            'merchant_private_key_path' => __DIR__ . '/cert/apiclient_key.pem',
            // 微信-商户API证书序列号-必填
            'merchant_certificate_serial' => '6645***D0744',
            // 微信-支付平台证书地址-必填，使用 composer 生成
            // composer exec CertificateDownloader.php -- -k ${apiV3key} -m ${mchId} -f ${mchPrivateKeyFilePath} -s ${mchSerialNo} -o ${outputFilePath}
            'platform_certificate_file_path' => __DIR__ . '/cert/wechatpay_61A5***F60C.pem',
            // 支付宝-同步通知地址-选填
            'return_url' => 'https://www.***.cn/alipay/return',
            // 支付宝-异步通知地址-选填
            'notify_url' => 'https://www.***.cn/alipay/notify',
            // 选填-默认为正常模式。可选为： MODE_NORMAL, MODE_SERVICE
            //'mode' => Pay::MODE_NORMAL,
        ]
    ],
];
/**********************支付配置******************************/

/****************************************************
 * 支付宝-app支付
 * @link https://opendocs.alipay.com/open/cd12c885_alipay.trade.app.pay
 ****************************************************/
try {
    $response = Payment::config($config)->alipay()->agreementSign([
        'access_params' => [
            'channel' => Alipay::QRCODE_CHANNEL, //【必选参数】当前支付接入方式， Alipay::ALIPAYAPP_CHANNEL（钱包h5页面签约） Alipay::QRCODE_CHANNEL(扫码签约) Alipay::QRCODEORSMS_CHANNEL(扫码签约或者短信签约)
        ],
        'period_rule_params' => [ //【必选参数】周期规则参数
            'period_type' => Alipay::PERIOD_TYPE_DAY, //【必选参数】周期类型period_type是周期扣款产品必填，枚举值为DAY和MONTH。 DAY即扣款周期按天计，MONTH代表扣款周期按自然月。
            'period' => 7,//【必选参数】周期数period是周期扣款产品必填。与另一参数period_type组合使用确定扣款周期，例如period_type为DAY，period=90，则扣款周期为90天。
            'execute_time' => date('Y-m-d', time() + 86400), // 【必选参数】首次执行时间execute_time是周期扣款产品必填，即商户发起首次扣款的时间。精确到日，格式为yyyy-MM-dd
            'single_amount' => 10.99, //【必选参数】单次扣款最大金额single_amount是周期扣款产品必填，即每次发起扣款时限制的最大金额，单位为元。商户每次发起扣款都不允许大于此金额。
            //'total_amount' => 100.99, //【可选参数】总金额限制，单位为元，不建议控制，除非业务有需求。如果传入此参数，商户多次扣款的累计金额不允许超过此金额。
            //'total_payments' => 10, //【可选参数】总扣款次数，不建议控制，除非业务有需求。如果传入此参数，商户累计发起扣款的次数不允许超过此次数。
        ],
        //'effect_time' => 600,//【可选参数】设置签约请求的有效时间，单位为秒，不建议控制，除非业务有需求。如传入600，商户发起签约请求到用户进入支付宝签约页面的时间差不能超过10分钟
        // 'external_agreement_no' => 'test20190701',//【可选参数】商户签约号，代扣协议中标示用户的唯一签约号（确保在商户系统中唯一）。 格式规则：支持大写小写字母和数字，最长32位。 商户系统按需传入，如果同一用户在同一产品码、同一签约场景下，签订了多份代扣协议，那么需要指定并传入该值。
        //'external_logon_id' => '13888888888',//【可选参数】用户在商户网站的登录账号，用于在签约页面展示，如果为空，则不展示
        //'sign_notify_url' => 'https://www.example.com/alipay/receiveSignNotify',//【可选参数】签约成功后商户用于接收异步通知的地址。如果不传入，签约与支付的异步通知都会发到外层notify_url参数传入的地址；如果外层也未传入，签约与支付的异步通知都会发到商户appid配置的网关地址。
    ]);
    //echo $response . PHP_EOL;
} catch (\InvalidArgumentException $e) {
    echo '参数错误：' . $e->getMessage() . PHP_EOL;
} catch (\Exception $e) {
    echo $e->getMessage();
}

try {
    $result = Payment::config($config)->alipay()->appPay([
        'subject' => '测试订单',
        'trade_no' => 'test' . time(),
        'amount' => '0.0001',
    ]);
    //echo $result . "\n";

} catch (\InvalidArgumentException $e) {
    echo '参数错误：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-周期扣款或商家扣款 注意：配置：product_code、sign_scene 必须配置
 * @link https://opendocs.alipay.com/open/e65d4f60_alipay.trade.app.pay
 ****************************************************/
try {
    $result = Payment::config($config)->alipay()->appPaySign([
        'subject' => '测试订单',//【必选参数】订单标题
        'trade_no' => 'test' . time(),//【必选参数】商户订单号
        'amount' => '0.01',//【必选参数】订单金额，单位:元
        'agreement_sign_params' => [// 签约参
            'access_params' => [
                'channel' => Alipay::ALIPAYAPP_CHANNEL, //【必选参数】当前支付接入方式， Alipay::ALIPAYAPP_CHANNEL（钱包h5页面签约） Alipay::QRCODE_CHANNEL(扫码签约) Alipay::QRCODEORSMS_CHANNEL(扫码签约或者短信签约)
            ],
            'period_rule_params' => [ //【必选参数】周期规则参数
                'period_type' => Alipay::PERIOD_TYPE_DAY, //【必选参数】周期类型period_type是周期扣款产品必填，枚举值为DAY和MONTH。 DAY即扣款周期按天计，MONTH代表扣款周期按自然月。
                'period' => 7,//【必选参数】周期数period是周期扣款产品必填。与另一参数period_type组合使用确定扣款周期，例如period_type为DAY，period=90，则扣款周期为90天。
                'execute_time' => date('Y-m-d', time()), // 【必选参数】首次执行时间execute_time是周期扣款产品必填，即商户发起首次扣款的时间。精确到日，格式为yyyy-MM-dd
                'single_amount' => 10.99, //【必选参数】单次扣款最大金额single_amount是周期扣款产品必填，即每次发起扣款时限制的最大金额，单位为元。商户每次发起扣款都不允许大于此金额。
                //'total_amount' => 100.99, //【可选参数】总金额限制，单位为元，不建议控制，除非业务有需求。如果传入此参数，商户多次扣款的累计金额不允许超过此金额。
                //'total_payments' => 10, //【可选参数】总扣款次数，不建议控制，除非业务有需求。如果传入此参数，商户累计发起扣款的次数不允许超过此次数。
            ],
            //'effect_time' => 600,//【可选参数】设置签约请求的有效时间，单位为秒，不建议控制，除非业务有需求。如传入600，商户发起签约请求到用户进入支付宝签约页面的时间差不能超过10分钟
            //'external_agreement_no' => 'test20190701',//【可选参数】商户签约号，代扣协议中标示用户的唯一签约号（确保在商户系统中唯一）。 格式规则：支持大写小写字母和数字，最长32位。 商户系统按需传入，如果同一用户在同一产品码、同一签约场景下，签订了多份代扣协议，那么需要指定并传入该值。
            //'external_logon_id' => '13888888888',//【可选参数】用户在商户网站的登录账号，用于在签约页面展示，如果为空，则不展示
            //'sign_notify_url' => 'https://www.example.com/alipay/receiveSignNotify',//【可选参数】签约成功后商户用于接收异步通知的地址。如果不传入，签约与支付的异步通知都会发到外层notify_url参数传入的地址；如果外层也未传入，签约与支付的异步通知都会发到商户appid配置的网关地址。
        ],
        'notify_url' => 'https://www.xxx.cn/alipay/notify',//【可选参数】如果支付配置 notify_url参数则支付函数配置该参数将不会生效，如若需要该参数生效请在支付配置中配置去掉notify_url参数或设置为 null 或 空字符串
    ]);
    echo $result . PHP_EOL;

} catch (\InvalidArgumentException $e) {
    echo '参数错误：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-独立签约后扣款 注意：配置：product_code、sign_scene 必须配置
 * @link https://opendocs.alipay.com/open/8bccfa0b_alipay.user.agreement.page.sign
 ****************************************************/
try {
    $response = Payment::config($config)->alipay()->agreementSign([
        'access_params' => [
            'channel' => Alipay::QRCODEORSMS_CHANNEL, //【必选参数】当前支付接入方式， Alipay::ALIPAYAPP_CHANNEL（钱包h5页面签约） Alipay::QRCODE_CHANNEL(扫码签约) Alipay::QRCODEORSMS_CHANNEL(扫码签约或者短信签约)
        ],
        'period_rule_params' => [ //【必选参数】周期规则参数
            'period_type' => Alipay::PERIOD_TYPE_DAY, //【必选参数】周期类型period_type是周期扣款产品必填，枚举值为DAY和MONTH。 DAY即扣款周期按天计，MONTH代表扣款周期按自然月。
            'period' => 7,//【必选参数】周期数period是周期扣款产品必填。与另一参数period_type组合使用确定扣款周期，例如period_type为DAY，period=90，则扣款周期为90天。
            'execute_time' => date('Y-m-d', time() + 86400), // 【必选参数】首次执行时间execute_time是周期扣款产品必填，即商户发起首次扣款的时间。精确到日，格式为yyyy-MM-dd
            'single_amount' => 10.99, //【必选参数】单次扣款最大金额single_amount是周期扣款产品必填，即每次发起扣款时限制的最大金额，单位为元。商户每次发起扣款都不允许大于此金额。
            //'total_amount' => 100.99, //【可选参数】总金额限制，单位为元，不建议控制，除非业务有需求。如果传入此参数，商户多次扣款的累计金额不允许超过此金额。
            //'total_payments' => 10, //【可选参数】总扣款次数，不建议控制，除非业务有需求。如果传入此参数，商户累计发起扣款的次数不允许超过此次数。
        ],
        //'effect_time' => 600,//【可选参数】设置签约请求的有效时间，单位为秒，不建议控制，除非业务有需求。如传入600，商户发起签约请求到用户进入支付宝签约页面的时间差不能超过10分钟
        'external_agreement_no' => 'test20190701',//【可选参数】商户签约号，代扣协议中标示用户的唯一签约号（确保在商户系统中唯一）。 格式规则：支持大写小写字母和数字，最长32位。 商户系统按需传入，如果同一用户在同一产品码、同一签约场景下，签订了多份代扣协议，那么需要指定并传入该值。
        //'external_logon_id' => '13888888888',//【可选参数】用户在商户网站的登录账号，用于在签约页面展示，如果为空，则不展示
        //'sign_notify_url' => 'https://www.example.com/alipay/receiveSignNotify',//【可选参数】签约成功后商户用于接收异步通知的地址。如果不传入，签约与支付的异步通知都会发到外层notify_url参数传入的地址；如果外层也未传入，签约与支付的异步通知都会发到商户appid配置的网关地址。
    ]);
    echo $response . PHP_EOL;
} catch (\InvalidArgumentException $e) {
    echo '参数错误：' . $e->getMessage();
} catch (\Exception $e) {
    echo '异常'.get_class($e).'---'.$e->getMessage().PHP_EOL;
}

/****************************************************
 * 支付宝-个人代扣协议查询
 * @link https://opendocs.alipay.com/open/3dab71bc_alipay.user.agreement.query
 ****************************************************/
try {
    $response = Payment::config($config)->alipay()->agreementQuery([
        'agreement_no' => '20235530057745627881',// 支付宝代扣协议号
        //'external_agreement_no' => '',// 项目内用户的唯一签约号
        //'alipay_open_id' => '',//用户的支付宝账号对应 的支付宝唯一用户号
        //'alipay_user_id' => '', // 用户的支付宝账号对应 的支付宝唯一用户号，以 2088 开头的 16 位纯数字 组成
        //'alipay_logon_id' => '',// 用户的支付宝登录账号，支持邮箱或手机号码格式。
        // 其他：https://opendocs.alipay.com/open/3dab71bc_alipay.user.agreement.query
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数错误：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-周期性扣款协议执行计划修改
 * @link https://opendocs.alipay.com/open/ed428330_alipay.user.agreement.executionplan.modify
 ****************************************************/
try {
    $agreementNo = '20235528056661302883';
    $deductTime = date('Y-m-d', strtotime('+1 month'));
    $memo = '用户已购买1个月会员，需延期扣款时间';
    $response = Payment::config($config)->alipay()->agreementExecutionplanModify($agreementNo, $deductTime, $memo);
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数错误：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-个人代扣协议解约
 * @link https://opendocs.alipay.com/open/b841da1f_alipay.user.agreement.unsign
 ****************************************************/
try {
    $response = Payment::config($config)->alipay()->agreementUnsign([
        'agreement_no' => '10245724140374641221',// 支付宝代扣协议号
        //'external_agreement_no' => '',// 项目内用户的唯一签约号
        //'alipay_open_id' => '',//用户的支付宝账号对应 的支付宝唯一用户号
        //'alipay_user_id' => '', // 用户的支付宝账号对应 的支付宝唯一用户号，以 2088 开头的 16 位纯数字 组成
        //'alipay_logon_id' => '',// 用户的支付宝登录账号，支持邮箱或手机号码格式。
        // 其他：https://opendocs.alipay.com/open/3dab71bc_alipay.user.agreement.query
    ]);
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数错误：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}
/****************************************************
 * 支付宝-web-pay
 * @link https://opendocs.alipay.com/open/59da99d0_alipay.trade.page.pay
 ****************************************************/
try {
    $result = Payment::config($config)->alipay()->webPay([
        'subject' => '测试订单',
        'trade_no' => 'test' . time(),
        'amount' => '0.01',
        'product_code' => 'FAST_INSTANT_TRADE_PAY',
    ]);
    echo $result . "\n";

} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-扫码支付
 * @link https://opendocs.alipay.com/open/8ad49e4a_alipay.trade.precreate
 ****************************************************/
try {
    $result = Payment::config($config)->alipay()->qrPay([
        'subject' => '测试订单',
        'trade_no' => 'test' . time(),
        'amount' => '0.01',
        'product_code' => 'QR_CODE_OFFLINE',
    ]);
    var_dump($result);

} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-交易查询
 * @link https://opendocs.alipay.com/open/82ea786a_alipay.trade.query
 ****************************************************/
try {
    $result = Payment::config($config)->alipay()->query([
        'out_trade_no' => 'test001',
        'trade_no' => 'test' . time(),
    ]);
    var_dump($result);

} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-交易撤销
 * @link https://opendocs.alipay.com/open/13399511_alipay.trade.cancel
 ****************************************************/
try {
    $result = Payment::config($config)->alipay()->cancel([
        'out_trade_no' => '20170320010101001',
    ]);
    var_dump($result);

} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-交易关闭
 * @link https://opendocs.alipay.com/open/e84f0d79_alipay.trade.close
 ****************************************************/
try {
    $result = Payment::config($config)->alipay()->close([
        'out_trade_no' => '20170320010101001',
    ]);
    var_dump($result);

} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 支付宝-申请交易账单
 * @link https://opendocs.alipay.com/open/e81ed5f1_alipay.data.dataservice.bill.downloadurl.query
 ****************************************************/
try {
    $response = Payment::config($config)->alipay()->downloadBill([
        'bill_date' => '2024-09-23',
        'bill_type' => 'ALL',
        'file_path' => __DIR__ . '/bill/2024-09-23.cvs',
    ]);
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

echo '---------微信支付----------' . PHP_EOL;
/****************************************************
 * 微信-app支付
 * @link https://pay.weixin.qq.com/docs/merchant/apis/in-app-payment/direct-jsons/app-prepay.html
 * @link https://pay.weixin.qq.com/docs/merchant/apis/in-app-payment/app-transfer-payment.html
 ****************************************************/
try {
    $result = Payment::config($config)->wechat()->appPay([
        'subject' => '测试订单',
        'trade_no' => 'test' . time() . rand(1000, 9999),
        'amount' => '0.01',
        'notify_url' => 'https://www.xxx.cn/wechat/notify',
    ]);
    var_dump($result);
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (PaymentException $e) {
    echo '返回码:' . $e->getCode() . PHP_EOL;
    echo '错误码:' . $e->getSubCode() . PHP_EOL;
    echo '返回码描述:' . $e->getMessage() . PHP_EOL;
} catch (\Exception $e) {
    echo 'Exception：一定要处理，可能还有其他异常(TransferException,ConnectException等异常)';
    echo $e->getMessage();
}

/****************************************************
 * 微信-jsapi支付
 * @link https://pay.weixin.qq.com/docs/merchant/apis/jsapi-payment/direct-jsons/jsapi-prepay.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->appJsApiPay([
        'subject' => '测试订单',
        'trade_no' => 'test' . time() . rand(1000, 9999),
        'amount' => '0.01',
        //'time_expire' => date('YmdHis', strtotime('+2 hours')),
        'notify_url' => 'https://www.nineton.cn/wechat/notify',
        'payer' => [
            'openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o',
        ]
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 微信-wap支付
 * @link https://pay.weixin.qq.com/docs/merchant/products/h5-payment/introduction.html
 * @link https://pay.weixin.qq.com/docs/merchant/apis/h5-payment/h5-transfer-payment.html
 * 商家参数格式有误，请联系商家解决
 * 请求头没有设置referer这个参数或referer参数域名与微信那边设置的安全域名不一致导致
 * @link https://pay.weixin.qq.com/docs/merchant/apis/h5-payment/direct-jsons/h5-prepay.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->wapPay([
        'subject' => '测试订单',
        'trade_no' => 'test' . time() . rand(1000, 9999),
        'amount' => '0.01',
        //'time_expire' => date('YmdHis', strtotime('+2 hours')),
        'notify_url' => 'https://www.nineton.cn/wechat/notify',
        'scene_info' => [
            'payer_client_ip' => '113.251.75.80',// 用户端实际ip
            'h5_info' => [
                'type' => 'Wap',
                'app_name' => '测试支付',
                'app_url' => 'https://www.nineton.cn',
            ]
        ]
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 微信-QR支付
 * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/direct-jsons/native-prepay.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->qrPay([
        'subject' => '测试订单',
        'trade_no' => 'test' . time() . rand(1000, 9999),
        'amount' => '0.01',
        //'time_expire' => date('YmdHis', strtotime('+2 hours')),
        'notify_url' => 'https://www.nineton.cn/wechat/notify',
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 微信-订单查询
 * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/query-by-wx-trade-no.html
 * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/query-by-out-trade-no.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->query([
        'transaction_id' => '4200002346202409236856351011',
        //'out_trade_no' => 'sxsrf20240923181031147811935',
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 微信-订单查询
 * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/close-order.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->close([
        'out_trade_no' => 'sxsrf20240923181031147811935',
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 微信-退款申请
 * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/create.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->refund([
        'out_trade_no' => '20240924000247980192186',
        'out_refund_no' => 'refund20240924000247980192186',
        'reason' => '测试退款',
        'amount' => [
            'refund' => 1,
            'total' => 1,
            'currency' => 'CNY'
        ],
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}

/****************************************************
 * 微信-退款查询
 * @linkhttps://pay.weixin.qq.com/docs/merchant/apis/native-payment/query-by-out-refund-no.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->refundQuery([
        'out_refund_no' => '20240903203024615505668',
    ]);
    // 业务可能需要处理其他 状态码
    if ($response->isSuccess()) {
        var_dump($response->getData());
    } else {
        echo '处理其他错误码' . PHP_EOL;
        echo '返回码:' . $response->getCode() . PHP_EOL;
        echo '错误码:' . $response->getSubCode() . PHP_EOL;
        echo '返回码描述:' . $response->getMsg() . PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}


/****************************************************
 * 微信-申请交易账单
 * @link https://pay.weixin.qq.com/docs/merchant/apis/native-payment/download-bill.html
 ****************************************************/
try {
    $response = Payment::config($config)->wechat()->downloadBill([
        'bill_date' => '2024-09-23',
        'bill_type' => 'ALL',
        'file_path' => __DIR__ . '/bill/2024-09-23.cvs',
    ]);
} catch (\InvalidArgumentException $e) {
    echo '参数：' . $e->getMessage();
} catch (\Exception $e) {
    echo $e->getMessage();
}