<?php

namespace Guirong\Database\Backup\Traits;


trait CallResultTrait
{
    /**
     * 内部错误信息
     * @var bool
     */
    protected $error = false;

    /**
     * 响应信息
     * @var bool
     */
    protected $response = null;

    public function judgeTrue()
    {
        return false == $this->getError();
    }

    protected function setError($value)
    {
        $this->error = $value;
        return $this;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setResponse($value)
    {
        $this->response = $value;
        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }
}
