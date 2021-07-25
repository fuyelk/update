<?php
// +---------------------------------------------------
// | 版本更新程序示例
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/07/25 22:48
// +---------------------------------------------------

use fuyelk\update\Update;
use fuyelk\update\UpdateException;


if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

$result = false;
try {
    $opt = [
        'config_path' => __DIR__ . '/temp/config.json',
        'install_path' => __DIR__ . '/temp',
        'domain' => 'http://www.example.com'
    ];
    $update = new Update($opt);
    $update->setDbConfig([
        'database' => 'test',
        'username' => 'root',
        'password' => 'root',
        'prefix' => 'tb_'
    ]);
    $update->updateCheck();
} catch (UpdateException $e) {
    if (404 == $e->getCode()) {
        var_dump('啊哦 服务器宕机了');
        exit();
    }
    var_dump($e->getMessage());
    exit();
}

if ($update->new_version_found) {
    try {
        $update->install();
    } catch (UpdateException $e) {
        var_dump($e->getMessage());
        exit();
    }
    var_dump(['msg' => '更新成功', 'info' => $update->getUpdateInfo()]);
    exit();
}
var_dump(['msg' => '当前已是最新版本', 'info' => $update->getUpdateInfo()]);
exit();

