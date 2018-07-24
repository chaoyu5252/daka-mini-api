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
	    ERROR_MONEY => '余额不足',
	    ERROR_TASK_CLICK_COUNT_LESS => '任务点击分数不能少于50份',
	    ERROR_TASK_CLICK_PRICE => '任务点击金额不正确',
	    ERROR_TASK_SHARE_PRICE => '任务分享金额不正确',
	    ERROR_TASK_SHARE_COUNT_LESS => '任务点击分数不能少于20份',
	    ERROR_TASK_SHARE_COUNT_MORE => '任务点击分数不能大于100份',
	    ERROR_TASK_CLICK_AND_SHARE_SUM_MORE => '分享和点击奖励总金额大于悬赏总金额',
	    ERROR_TASK_NO_EXIST => '任务不存在',
	    ERROR_TASK_FINISHED => '任务已经结束',
	    ERROR_TASK_RECORD_NO_EXIST => '任务记录不存在',
	    ERROR_PAY_ITEM => '错误支付类型',
	    ERROR_UPLOAD => '上传文件失败',
	    ERROR_UPLOAD_FILE_TYPE => '错误的文件类型',
	    ERROR_LOGIC => '服务器内部错误'
    );
}