<?php

namespace Simplephp\PaymentSdk\Util;

class Helper
{
    /**
     * 公钥
     */
    const KEY_TYPE_PUBLIC = 'public';

    /**
     * 私钥
     */
    const KEY_TYPE_PRIVATE = 'private';

    /**
     * 检测日期格式
     * @param $dateTime
     * @param string $format
     * @return bool
     */
    public static function isValidDateTime($dateTime, string $format = 'Y-m-d H:i:s'): bool
    {
        $info = date_parse_from_format($format, $dateTime);
        return 0 == $info['warning_count'] && 0 == $info['error_count'];
    }

    /**
     * 验证给定的密钥是否为有效的私钥或公钥
     * @param string $keyContent 密钥内容
     * @param string $keyType 密钥类型 ("private" 或 "public")
     * @param string|null $passphrase 私钥的密码短语（如果是私钥并且需要时）
     * @return bool 如果密钥有效则返回true，否则返回false
     */
    public static function validateKey(string $keyContent, string $keyType = self::KEY_TYPE_PRIVATE, string $passphrase = null): bool
    {
        $format = [
            self::KEY_TYPE_PUBLIC => "-----BEGIN PUBLIC KEY-----\n%s\n-----END PUBLIC KEY-----\n",
            self::KEY_TYPE_PRIVATE => "-----BEGIN PRIVATE KEY-----\n%s\n-----END PRIVATE KEY-----\n",
        ];
        if (!isset($format[$keyType])) {
            return false;
        }
        $keyContent = sprintf($format[$keyType], $keyContent);
        // 根据密钥类型验证密钥
        if ($keyType === self::KEY_TYPE_PRIVATE) {
            $key = openssl_pkey_get_private($keyContent, $passphrase);
        } elseif ($keyType === self::KEY_TYPE_PUBLIC) {
            $key = openssl_pkey_get_public($keyContent);
        } else {
            return false;
        }
        if ($key === false) {
            return false;
        }
        openssl_free_key($key);
        return true;
    }
}