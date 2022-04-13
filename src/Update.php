<?php
// +---------------------------------------------------
// | 版本更新程序
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/07/25 22:30
// +---------------------------------------------------

namespace fuyelk\update;

use fuyelk\db\Db;
use fuyelk\install\Install;
use fuyelk\install\InstallException;

class Update
{
    /**
     * @var string 服务器接口地址
     */
    private $server_url = '';

    /**
     * @var string APPID
     */
    private $appid = '';

    /**
     * @var string APPSECRET
     */
    private $appsecret = '';

    /**
     * @var string domain
     */
    private $domain = '';

    /**
     * @var string 当前版本号
     */
    private $current_version = '0.0.0.0';

    /**
     * @var string 安装路径
     */
    private $install_path = '';

    /**
     * @var string 配置文件路径
     */
    private $config_file = __DIR__ . '/config.json';

    /**
     * @var array 更新信息
     */
    private $updateInfo = [];

    /**
     * @var bool 发现新版本标识
     */
    public $new_version_found = false;

    /**
     * @var array 数据库配置
     */
    private $dbConfig = [];

    /**
     * @return array
     */
    public function getUpdateInfo()
    {
        return $this->updateInfo;
    }

    /**
     * 配置数据库
     * @param array $config ['type','host','database','username','password','port','prefix']
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function setDbConfig(array $config)
    {
        $this->dbConfig = $config;
    }

    /**
     * Update constructor.
     * @param string $install_path 安装目录
     * @param string $domain [域名]
     * @param string $config_file [配置文件]
     * @throws UpdateException
     */
    public function __construct($install_path, $domain = '', $config_file = '')
    {
        if (empty($install_path)) {
            throw new UpdateException('未设置更新安装目录');
        }

        $this->config_file = $config_file ?: $this->config_file;
        if (!is_dir(dirname($this->config_file))) {
            throw new UpdateException('配置文件路径不存在');
        }

        $this->install_path = $install_path;
        $this->domain = $domain ?? '';
        $this->initConfig();
    }

    /**
     * 检查配置
     * @throws UpdateException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private function initConfig()
    {
        $config = $this->getRedisConf();
        if (empty($config['server_url'])) {
            throw new UpdateException('服务器接口地址配置有误');
        }
        $this->server_url = $config['server_url'];

        if (empty($config['appid'])) {
            throw new UpdateException('APPID配置有误');
        }
        $this->appid = $config['appid'];

        if (empty($config['appsecret'])) {
            throw new UpdateException('APPSECRET配置有误');
        }
        $this->appsecret = $config['appsecret'];

        if (empty($config['current_version'])) {
            throw new UpdateException('APP版本号配置有误');
        }
        $this->current_version = $config['current_version'];
    }

    /**
     * 获取redis配置
     * @param bool|string $key [配置名]
     * @return array|mixed
     * @throws UpdateException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function getRedisConf($key = false)
    {
        // 验证配置文件的有效性
        if (is_file($this->config_file)) {
            $data = file_get_contents($this->config_file);
            if (!empty($data) and $config = json_decode($data, true)) {
                return $key ? ($config[$key] ?? null) : $config;
            }
        }
        $this->setConfig();
        throw new UpdateException('初始化配置请修改[' . str_replace('\\', '/', $this->config_file) . ']配置文件');
    }

    /**
     * 创建配置文件
     * @param array $config 配置内容
     * @return bool
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    protected function setConfig($config = [])
    {
        $config = $config ?: [
            'server_url' => $this->server_url,
            'appid' => $this->appid,
            'appsecret' => $this->appsecret,
            'current_version' => $this->current_version,
        ];

        if (!is_dir(dirname($this->config_file))) {
            mkdir(dirname($this->config_file), 0755, true);
        }

        $fp = fopen($this->config_file, 'w');
        fwrite($fp, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fclose($fp);
        return true;
    }

    /**
     * 网络请求
     * @param string $url 请求地址
     * @param string $method 请求方式：GET/POST
     * @param array $data 请求数据
     * @return bool|string
     * @throws UpdateException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private function request($url, $method = 'GET', $data = [])
    {
        $ts = time();
        $data['appid'] = $this->appid;
        $data['ts'] = $ts;
        $data['token'] = md5($this->appid . $ts . $this->appsecret);
        $addHeader = [];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_ACCEPT_ENCODING => 'gzip,deflate',
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_REFERER => '',
            CURLOPT_USERAGENT => "Mozilla / 5.0 (Windows NT 10.0; Win64; x64)",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($data) {
            $data = http_build_query($data);
            array_push($addHeader, 'Content-type:application/x-www-form-urlencoded');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $addHeader);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new UpdateException('网络错误', 404);
        }
        return $response;
    }

    /**
     * 检查更新
     * @throws UpdateException
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 22:51
     */
    public function updateCheck()
    {
        $data = [
            'version' => $this->current_version,
            'domain' => $this->domain,
        ];

        // 请求更新接口
        $response = $this->request($this->server_url, 'POST', $data);
        if (empty($response)) {
            throw new UpdateException('未查询到版本信息');
        }

        // 校验更新接口信息
        $res = json_decode($response, true);
        if (empty($res) || !isset($res['code']) || !isset($res['msg'])) {
            throw new UpdateException('未查询到版本信息');
        }

        // 接口状态不能为0
        if (empty($res['code']) || empty($res['data']) || !isset($res['data']['new_version_found'])) {
            throw new UpdateException($res['msg']);
        }

        $this->updateInfo = $res['data'];
        $this->new_version_found = !empty($res['data']['new_version_found']);

        // 强制更新
        if ($this->new_version_found && !empty($res['data']['enforce'])) {
            return $this->install(true);
        }
        return true;
    }

    /**
     * 安装
     * @param bool $recheck 完成好是否再次检查更新
     * @return bool
     * @throws UpdateException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public function install(bool $recheck = false)
    {
        $info = $this->updateInfo;
        if (empty($info)) {
            throw new UpdateException('请先检查更新');
        }

        if (empty($info['new_version_found']) || empty($this->updateInfo['new_version'])) {
            throw new UpdateException('已是最新版本无需更新');
        }

        if (empty($info['download_url'])) {
            throw new UpdateException('没有找到下载地址');
        }

        // 安装更新
        try {

            if (!empty($this->dbConfig)) {
                Install::setDbConfig($this->dbConfig);
            }

            Install::setRootPath($this->install_path);

            if (isset($info['package_size'])) {
                Install::setPackageSize(intval($info['package_size']));
            }

            if (isset($info['md5'])) {
                Install::setPackageMd5($info['md5']);
            }

            Install::install($info['download_url']);
        } catch (InstallException $e) {
            throw new UpdateException($e->getMessage());
        }

        $this->current_version = $this->updateInfo['new_version'];
        $config = [
            'server_url' => $this->server_url,
            'appid' => $this->appid,
            'appsecret' => $this->appsecret,
            'current_version' => $this->current_version,
            'update_time' => date('Y-m-d H:i:s'),
            'update_info' => $this->updateInfo
        ];

        $this->setConfig($config);

        if ($recheck) {
            return $this->updateCheck();
        }

        return true;
    }
}