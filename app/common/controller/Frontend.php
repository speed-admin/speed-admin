<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/21
 */

namespace app\common\controller;

use app\frontend\middleware\ViewNode;
use app\backend\service\AuthService;
use app\BaseController;
use app\common\traits\Curd;
use app\common\traits\Jump;
use fun\addons\Controller;
use think\App;
use think\exception\ValidateException;
use think\facade\Cookie;
use think\facade\Lang;
use think\facade\View;
use think\helper\Str;

class Frontend extends BaseController
{
    use Jump,Curd;

    protected $middleware = [
        ViewNode::class,
    ];

    /**
     * @var
     * 模型
     */
    protected $modelClass;

    /**
     * @var
     * 页面大小
     */
    protected $pageSize;
    /**
     * @var
     * 页数
     */
    protected $page;

    /**
     * 模板布局, false取消
     * @var string|bool
     */
    protected $layout = 'layout/main';

    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';
    /**
     * 下拉选项条件
     * @var string
     */
    protected $selectMap =[];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    protected $allowModifyFields = [
        'status',
        'sort',
        'title',
    ];
    /**
     * 关联join搜索
     * @var array
     */
    protected $joinSearch = [];

    /**
     * selectpage 字段
     * @var string[]
     */
    protected $selectpageFields = ['*'];

    /**
     * 隐藏字段
     * @var array
     */
    protected $hiddenFields = [];

    /**
     * 可见字段
     * @var array
     */
    protected $visibleFields = [];


    /**
     * 是否开启数据限制
     * 表示按权限判断/仅限个人
     */
    protected $dataLimit = false;


    public function __construct(App $app)
    {
        parent::__construct($app);
        //过滤参数
        $this->layout && $this->app->view->engine()->layout($this->layout);
        $controller = parse_name($this->request->controller(),1);
        $controller = strtolower($controller);
        if($controller!=='ajax'){
            $this->loadlang($controller,'');
        }
        //过滤参数
        $this->pageSize = input('limit', 15);
        $this->page = input('page', 1);
    }

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub

    }


    //自动加载语言
    protected function loadlang($name,$app)
    {
        $lang = cookie(config('lang.cookie_var'));
        if(!empty($lang) && Str::contains($lang,'../')){
            return false;
        }
        if($app){
            $res =  Lang::load([
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang  . '.php'
            ]);
        }else{
            $res = Lang::load([
                $this->app->getAppPath() . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
                $this->app->getAppPath() . 'lang' . DS . $lang . '.php'
            ]);
        }
        return $res;
    }

    /**
     * @param array $data
     * @param array|string $validate
     * @param array $message
     * @param bool $batch
     * @return array|bool|string|true
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        try {
            parent::validate($data, $validate, $message, $batch);
            $this->checkToken();
        } catch (ValidateException $e) {
            $this->error($e->getMessage());
        }
        return true;
    }

    /**
     * 检测token 并刷新
     */
    protected function checkToken()
    {
        $check = $this->request->checkToken('__token__', $this->request->param());
        if (false === $check) {
            $this->error(lang('Token verify error'), '', ['__token__' => $this->request->buildToken()]);
        }
    }
    /**
     * 刷新Token
     */
    protected function token()
    {
        return $this->request->buildToken();
    }


    //是否登录
    protected function isLogin()
    {
        if (session('member.id')) {
            return session('member');
        } else {
            return false;
        }
    }


}
