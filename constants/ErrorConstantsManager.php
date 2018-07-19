<?php

namespace Fichat\Constants;

class ErrorConstantsManager
{
    public static $errorMessageList = array(
        ERROR_SUCCESS => '操作成功',
        ERROR_TOKEN => '请重新登录',
        ERROR_TOKEN_TIMEOUT => '请重新登录',
        ERROR_WX_DECRYPT => '初始化用户数据失败, 请重试',
	    ERROR_LOGIN_VERIFY => '登录验证失败',
	    ERROR_LOGIC => '服务器内部错误'
    );
}