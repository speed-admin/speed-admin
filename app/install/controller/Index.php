<?php

namespace app\install\controller;

use app\common\traits\Jump;
use think\App;
use app\BaseController;
use think\facade\Config;
use think\facade\Db;
use think\facade\View;

class Index extends BaseController
{
    use Jump;
    //开发模式
    protected $app_debug = false;

    protected $config;
    //错误信息
    protected $msg = '';
    //安装文件
    protected $lockFile;
    //数据库
    protected $databaseConfigFile;
    protected $envFile;
    /**
     * 安装中要执行的 SQL 脚本文件清单.
     * 自定义的SQL脚本放在controller同级的sql文件夹,将文件名添加到这个数组中,务必注意脚本依赖顺序,因为系统会按照数组里的顺序依次执行.
     */
    protected $sqlFileDir = '';
    //mysql版本
    protected $mysqlVersion = '5.7';
    //database模板
    protected $databaseTpl = '';

    public function __construct(App $app)
    {
        parent::__construct($app); // TODO: Change the autogenerated stub
        $this->databaseConfigFile = config_path() . "database.php";
        $this->envFile = root_path() . ".env";
        $this->lockFile = public_path() . "install.lock";
        $this->databaseTpl = app_path() . "view/tpl/database.tpl";
        $this->envTpl = app_path() . "view/tpl/env.example";
        $this->sqlFileDir = app_path() . "sql";
        $this->config = [
            'siteName' => "FunAdmin",
            'siteVersion' => config('app.version'),
            'tablePrefix' => "fun_",
            'runtimePath' => runtime_path(),
            'lockFile' => $this->lockFile,
        ];
        set_time_limit(0);
        if (request()->action() != 'step4' && file_exists($this->lockFile)) {
            $this->error('当前版本已经安装了，如果需要重新安装请先删除install.lock', '/');
        }
        View::assign('config', $this->config);
    }

    public function index()
    {
        return redirect('index/step1');
    }

    public function step1()
    {
        return view('step1');
    }

    public function step2()
    {
        $data['php_version'] = PHP_VERSION;
        $data['pdo'] = extension_loaded("PDO");
        $data['mysqli'] = extension_loaded("mysqli");
        $data['open_basedir'] = ini_get('open_basedir');
        $data['database'] = is_really_writable($this->databaseConfigFile);
        $data['gd_info'] = function_exists('gd_info') || class_exists('Imagick', false);
        return view('step2', ['data' => $data]);

    }

    public function step3()
    {
        // 检测环境页面
        if (request()->action() === 'step3' && request()->isGet()) {
            return view('step3');
        }
        if (request()->action() === 'step3' && request()->isPost()) {
            //执行安装
            $this->app_debug = request()->post('app_debug')? true : false;
            $db['host'] = request()->post('hostname') ? request()->post('hostname') : '127.0.0.1';
            $db['port'] = request()->post('port') ?: '3306';
            //判断是否在主机头后面加上了端口号
            $hostData = explode(":", $db['host']);
            if (isset($hostData) && $hostData && is_array($hostData) && count($hostData) > 1) {
                $db['host'] = $hostData[0];
                $db['port'] = $hostData[1];
            }
            //mysql的账户相关
            $db['username'] = request()->post('username') ?: 'root';
            $db['password'] = request()->post('password') ?: 'root';
            $db['database'] = request()->post('database') ?: 'funadmin';
            $db['prefix'] = request()->post('prefix');
            $admin['username'] = request()->post('adminUserName') ?: 'admin';
            $admin['password'] = request()->post('adminPassword') ?: '123456';
            $admin['repassword'] = request()->post('rePassword') ?: '123456';
            $admin['email'] = request()->post('email') ?: 'admin@admin.com';
            if (file_exists($this->lockFile)) {
                $this->error('当前版本已经安装了，如果需要重新安装请先删除install.lock');
            }
            //php 版本
            if (version_compare(PHP_VERSION, '8.0.0', '<')) {
                $this->error('当前版本(" . PHP_VERSION . ")过低，请使用PHP8.0.0以上版本');
            }
            if (!extension_loaded("PDO")) {
                $this->error('当前未开启PDO，无法进行安装');
            }
            //判断两次输入是否一致
            if ($admin['password'] != $admin['repassword']) {
                $this->error('两次输入密码不一致！');
            }
            if (!preg_match('/^[0-9a-z_$]{6,16}$/i', $admin['password'])) {
                $this->error('密码必须6-16位,不能有中文和空格');

            }
            if (!preg_match("/^\w+$/", $admin['username'])) {
                $this->error('用户名只能输入字母、数字、下划线！');
            }
            if (strlen($admin['username']) < 3 || strlen($admin['username']) > 12) {
                $this->error('用户名请输入3~12位字符！');
            }
            if (strlen($admin['password']) < 6 || strlen($admin['password']) > 16) {
                $this->error('密码请输6~16位字符！');
            }

            // 连接数据库
            $link = @new \mysqli("{$db['host']}:{$db['port']}", $db['username'], $db['password']);
            if (mysqli_connect_errno()) {
                $this->error(mysqli_connect_error());
            }
            $link->query("SET NAMES 'utf8mb4'");
//            需要超管
//            $link->query('set global wait_timeout=2147480');
//            $link->query("set global interactive_timeout=2147480");
//            $link->query("set global max_allowed_packet=104857600");
            //版本
            if (version_compare($link->server_info, $this->mysqlVersion, '<')) {
                $this->error("MySQL数据库版本不能低于{$this->mysqlVersion},请将您的MySQL升级到{$this->mysqlVersion}及以上");
            }
            // 创建数据库并选中
            if (!$link->select_db($db['database'])) {
                $create_sql = 'CREATE DATABASE IF NOT EXISTS ' . $db['database'] . ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
                if (!$link->query($create_sql)) {
                    $this->error('创建数据库失败');
                }
            }
            $link->select_db($db['database']);
            // 写入数据库
            $config = Config::get('database');
            $config['connections']['mysql'] = [
                'type'      => 'mysql',
                'hostname'  => $db['host'],
                'database'  => $db['database'],
                'username'  => $db['username'],
                'password'  => $db['password'],
                'hostport'  => $db['port'],
                'params'    => [],
                'charset'   => 'utf8mb4'
            ];
            Config::set($config, 'database');
            try {
                $instance = Db::connect();
                $instance->execute("SELECT 1");     //如果是【数据】增删改查直接运行
                //逐个执行SQL脚本
                $sqlFiles = glob($this->sqlFileDir. '/*');
                foreach ($sqlFiles as $i => $value) {
                    if(!is_file($value)) continue;
                    //检测能否读取安装文件
                    $sql = @file_get_contents($value);
                    if (!$sql) {
                        $this->error("无法读取{$value}文件，请检查是否有读权限");
                    }

                    //替换数据表前缀
                    $sql = str_replace(["`fun_", 'CREATE TABLE'], ["`{$db['prefix']}", 'CREATE TABLE IF NOT EXISTS'], $sql);
                    $instance->getPdo()->exec($sql);
                    sleep(2);
                }
                $password = password($admin['password']);
                $instance->execute("UPDATE {$db['prefix']}admin SET `email`='{$admin['email']}',`username` = '{$admin['username']}',`password` = '{$password}' WHERE `username` = 'admin'");
                $instance->execute("UPDATE {$db['prefix']}member SET `email`='{$admin['email']}',`username` = '{$admin['username']}',`password` = '{$password}' WHERE `username` = 'admin'");
            } catch (\PDOException $e) {
                $this->error($e->getMessage());
            }catch(\Exception $e){
                $this->error($e->getMessage());
            }
            //替换数据库相关配置
            $putDatabase = str_replace(
                ['%hostname%', '%database%', '%username%', '%password%', '%port%', '%prefix%'],
                [$db['host'],$db['database'], $db['username'], $db['password'], $db['port'], $db['prefix']],
                file_get_contents($this->databaseTpl));
            $putConfig = @file_put_contents($this->databaseConfigFile, $putDatabase);
            if (!$putConfig) {
                $this->error('安装失败、请确定database.php是否有写入权限');
            }
            if($this->app_debug){
                $putEnv = str_replace(
                    ['%debug%','%hostname%', '%database%', '%username%', '%password%', '%port%', '%prefix%'],
                    [$this->app_debug,$db['host'],$db['database'], $db['username'], $db['password'], $db['port'], $db['prefix']],
                    file_get_contents($this->envTpl));
                $putConfig = @file_put_contents($this->envFile, $putEnv);
                if (!$putConfig) {
                    $this->error('安装失败、请确定目录是否有写入权限');
                }
            }
            $result = @touch($this->lockFile);
            if (!$result) {
                $this->error("安装失败、请确定install.lock是否有写入权限");
            }
            $adminUser['username'] = $admin['username'];
            $adminUser['password'] = $admin['password'];
            session('admin_install', $adminUser);
            $this->success('安装成功,安装后请重新启动程序');
        }
    }

    public function step4()
    {
        //完成安装
        if (request()->isPost()) {
            session('admin_install', '');
            $this->success('OK');
        }
        return view('step4');
    }


}
