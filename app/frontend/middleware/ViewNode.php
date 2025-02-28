<?php

namespace app\frontend\middleware;

use think\App;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Request;
use think\facade\View;
use think\helper\Str;

class ViewNode
{
    public function handle($request, \Closure $next)
    {
        [$appname, $controllername, $actionname] = [app('http')->getName(), $request->controller(true), Request::action()];
        $controllers = explode('.', $controllername);
        $jsname = '';
        foreach ($controllers as $vo) {
            empty($jsname) ? $jsname = $vo : $jsname .= '/' . $vo;
        }
        $actionname = strtolower($actionname);
        $requesturl = "{$appname}/{$controllername}/{$actionname}";
        $autojs = file_exists(app()->getRootPath()."public".DS."static".DS."{$appname}".DS."js".DS."{$jsname}.js") ? true : false;
        $jspath ="{$appname}/js/{$jsname}.js";
        $config = [
            'appname'               => $appname,
            'moduleurl'             => rtrim(__u("/{$appname}", [], false), '/'),
            'module'                => '/frontend/',
            'controllername'        => $controllername,
            'actionname'            => $actionname,
            'requesturl'            => $requesturl,
            'jspath'                => "{$jspath}",
            'autojs'                => $autojs,
            'superAdmin'            => session('member.id') == 1,
            'lang'                  => strip_tags(Lang::getLangset()),
            'site'                  => syscfg('site'),
            'upload'                => syscfg('upload'),
            'editor'                => syscfg('editor'),
            'public_ajax_url'         =>config('funadmin.public_ajax_url'),

        ];
        View::assign('CONFIG',$config);
        $request->appname =$appname;
        return $next($request);
    }

    //中间件支持定义请求结束前的回调机制，你只需要在中间件类中添加end方法。
    public function end(\think\Response $response)
    {
        // 回调行为
    }
}
