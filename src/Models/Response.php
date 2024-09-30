<?php

namespace Simplephp\PaymentSdk\Models;

class Response
{
    /**
     * @var string 返回码
     */
    public $code;

    /**
     * @var string 明细返回码
     */
    public $subCode;
    /**
     * @var string 返回消息
     */
    public $msg;
    /**
     * @var string 明细返回消息
     */
    public $subMsg;
    /**
     * @var string 数据
     */
    public $data;

    /**
     * @var string 状态
     */
    private $status;

    /**
     * @var string 成功状态
     */
    const STATUS_SUCCESS = 'success';

    /**
     * @var string 失败状态
     */
    const STATUS_ERROR = 'error';

    /**
     * @param $status
     * @param $code
     * @param $msg
     * @param $data
     * @param $subCode
     * @param $subMsg
     */
    public function __construct($status, $code, $msg, $data = null, $subCode = null, $subMsg = null)
    {
        $this->status = $status;
        $this->code = $code;
        $this->msg = $msg;
        $this->data = $data;
        $this->subCode = $subCode;
        $this->subMsg = $subMsg;
    }

    /**
     * setStatus
     * @param $status
     * @return void
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * setCode
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getSubCode()
    {
        return $this->subCode;
    }

    /**
     * @param string $subCode
     */
    public function setSubCode($subCode): void
    {
        $this->subCode = $subCode;
    }

    /**
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * @param string $msg
     */
    public function setMsg(string $msg): void
    {
        $this->msg = $msg;
    }

    /**
     * @return string
     */
    public function getSubMsg()
    {
        return $this->subMsg;
    }

    /**
     * @param string $subMsg
     */
    public function setSubMsg($subMsg): void
    {
        $this->subMsg = $subMsg;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * @return false|string
     */
    public function __toString()
    {
        return json_encode($this);
    }
}