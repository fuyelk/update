<?php

namespace fuyelk\update;

use Exception;
use Throwable;

class UpdateException extends Exception
{

    // 操作警告
    const WARNING_CONFIG_UNINITIALIZED = 3001;
    const WARNING_UPDATE_NOT_CHECKED = 3002;
    const WARNING_IS_LATEST_VERSION = 3003;

    // 配置错误
    const ERROR_INSTALL_PATH_NOT_SET = 4001;
    const ERROR_SERVER_URL = 4002;
    const ERROR_APPID = 4003;
    const ERROR_APP_SECRET = 4004;
    const ERROR_CURRENT_VERSION = 4005;
    const ERROR_DOWNLOAD_URL_NOT_FOUND = 4006;
    const ERROR_NETWORK = 404;

    // 服务器错误
    const SERVER_ERROR = 500;
    const SERVER_NOTICE = 5001;

    // 安装错误
    const INSTALL_ERROR = 6001;

    // 警告列表
    public static $warningList = [
        self::WARNING_CONFIG_UNINITIALIZED => '配置文件未初始化',
        self::WARNING_UPDATE_NOT_CHECKED => '请先检查更新',
        self::WARNING_IS_LATEST_VERSION => '已是最新版本无需更新',
    ];

    // 错误列表
    public static $errorList = [
        self::ERROR_INSTALL_PATH_NOT_SET => '未设置更新安装目录',
        self::ERROR_SERVER_URL => '服务器接口地址配置有误',
        self::ERROR_APPID => 'APPID配置有误',
        self::ERROR_APP_SECRET => 'APPSECRET配置有误',
        self::ERROR_CURRENT_VERSION => 'APP版本号配置有误',
        self::ERROR_NETWORK => '网络错误',
        self::ERROR_DOWNLOAD_URL_NOT_FOUND => '未找到下载地址',
    ];

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = self::$errorList[$code] ?? $message;
        $message = self::$warningList[$code] ?? $message;
        parent::__construct($message, $code, $previous);
    }
}