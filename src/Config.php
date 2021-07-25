<?php
// +---------------------------------------------------
// | 配置管理
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/07/25 21:37
// +---------------------------------------------------

namespace fuyelk\update;

Class Config
{
    /**
     * 读取配置信息
     * @param string $path
     * @return bool|mixed
     * @throws UpdateException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function get(string $path = '', string $name = '')
    {
        if (empty($path)) {
            $path = __DIR__ . '/conf.php';
        }

        if (!is_file($path)) return false;

        $type = strtolower(self::getExtName($path));

        // PHP文件直接返回执行结果
        if ('.php' == $type) {
            $config = require $path;
            if (!empty($name)) {
                if (array_key_exists($name, $config)) {
                    return $config[$name];
                }
                return null;
            }
            return $config;
        }

        $data = file_get_contents($path);
        if (empty($data)) return false;

        // JSON文件解析后返回
        if ('.json' == $type) {
            $config = json_decode($data, true);
            if (!empty($name)) {
                if (array_key_exists($name, $config)) {
                    return $config[$name];
                }
                return null;
            }
            return $config;
        }

        throw new UpdateException('不支持的配置文件');
    }

    /**
     * 创建配置文件
     * @param array $content 配置内容,$$:开头的值原样输出
     * @param string $path 文件路径,可以是PHP或JSON文件,缺省为json
     * @param int $tabLevel [左侧制表符数量（建议缺省）]
     * @return string|bool
     * @throws UpdateException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function set(array $content, string $path = '', int &$tabLevel = 1)
    {
        if (!is_string($path)) {
            throw new UpdateException('配置文件路径有误');
        }

        $str = "";

        if (empty($path)) {
            $path = __DIR__ . '/config.json';
        }

        // false为递归写入中
        if ('false' != $path) {

            $type = strtolower(self::getExtName($path));

            // JSON文件直接写入即可
            if ('.json' == $type) {
                $fp = fopen($path, 'w');
                fwrite($fp, json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                fclose($fp);
                return true;
            }

            // PHP文件,写文件头
            if ('.php' == $type) {
                $str .= "<?php\n\nreturn\t[\n";
            }else{
                throw new UpdateException('不支持的配置文件格式');
            }
        }

        // 遍历配置信息，逐行拼接
        if (is_array($content)) {
            foreach ($content as $key => $value) {

                // 创建左侧制表符
                $tabLeft = str_pad("\t", $tabLevel, "\t");

                // 定义键
                if (is_string($key)) {
                    $confKey = "'{$key}'";
                } else {
                    $confKey = $key;
                }

                // 值为数组则递归检查
                if (is_array($value) && !empty($value)) {

                    // 创建数组的键及左括号并换行
                    $str .= "{$tabLeft}{$confKey}\t=>\t[\n";

                    // 增加一级左侧缩进
                    $tabLevel++;

                    // 递归获取数组内容
                    $str .= self::set($value, 'false', $tabLevel);

                    // 数组结束回退一级左侧缩进
                    $tabLevel--;

                    // 创建数组右括号并换行
                    $str .= str_pad("\t", $tabLevel, "\t") . "],\n";
                    continue;
                }

                // 值不是数组，则根据值的类型创建值
                if (is_string($value)) {
                    $value = str_replace('\'', '\\\'', $value);
                    $confValue = "'{$value}'";
                } elseif (is_bool($value)) {
                    $confValue = $value ? "true" : "false";
                } elseif (is_null($value)) {
                    $confValue = "null";
                } elseif (is_array($value) && empty($value)) {
                    $confValue = "[]";
                } else {
                    $confValue = $value;
                }

                // 拼接这一行的值
                $str .= "{$tabLeft}{$confKey}\t=>\t{$confValue},\n";
            }
        }

        // false为递归写入中
        if ('false' != $path) {
            $str .= "];";
            $fp = fopen($path, 'w');
            fwrite($fp, $str);
            fclose($fp);
            return true;
        }

        return $str;
    }

    /**
     * 获取文件扩展名
     * @param string $file
     * @return string
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private static function getExtName(string $file): string
    {
        $arrExt = explode('.', basename($file));
        if (count($arrExt) > 1) {
            $ext = end($arrExt);
            return '.' . $ext;
        }
        return basename($file);
    }
}