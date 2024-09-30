<?php
/**
 * 检测日期格式
 * @param $dateTime
 * @param string $format
 * @return bool
 */
function isValidDateTime($dateTime, string $format = 'Y-m-d H:i:s'): bool
{
    $info = date_parse_from_format($format, $dateTime);
    return 0 == $info['warning_count'] && 0 == $info['error_count'];
}