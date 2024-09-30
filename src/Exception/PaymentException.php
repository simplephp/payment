<?php

namespace Simplephp\PaymentSdk\Exception;

class PaymentException extends \Exception
{
    /**
     * @var string 子错误码
     */
    protected $subCode;

    /**
     * @var string 子错误信息
     */
    protected $subMsg;

    public function __construct($code, $subCode, $message = "", $subMsg = '', \Throwable $previous = null)
    {
        $this->subCode = $subCode;
        $this->subMsg = $subMsg;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getSubCode(): string
    {
        return $this->subCode;
    }

    /**
     * @return string
     */
    public function getSubMsg(): string
    {
        return $this->subMsg;
    }
}