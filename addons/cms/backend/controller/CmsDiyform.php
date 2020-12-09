<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace addons\cms\backend\controller;

use app\common\controller\AddonsBackend;
use app\common\traits\Curd;
use think\facade\Request;
use think\facade\View;
use think\App;
use addons\cms\common\model\CmsDiyform as CmsDiyformModel;
use think\Validate;

class CmsDiyform extends AddonsBackend
{
    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CmsDiyformModel();
    }

    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
        return view();

    }

    public function add()
    {
        if (Request::isPost()) {
            $post = $this->request->post();
            try{
                $this->validate($post, 'CmsLink');
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }


            $res = CmsDiyformModel::create($post);
            if ($res) {
                $this->success(lang('add success'),url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        }
        $view = [
            'info' => '',
            'title' => lang('add'),
        ];
        View::assign($view);
        return view();
    }

    public function edit()
    {
        if (Request::isPost()) {
            $post = $this->request->post();
            try {
                $this->validate($post, 'CmsLink');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $res = CmsDiyformModel::update($post);
            if ($res) {
                $this->success(lang('operation success'), url('index'));
            } else {
                $this->error(lang('edit fail'));
            }
        }
        $info = CmsDiyformModel::find(Request::get('id'));
        $view = [
            'info' => $info,
            'title' => lang('edit'),
        ];
        View::assign($view);
        return view('add');

    }
    public function delete()
    {
        $ids = $this->request->post('ids');
        if ($ids) {
            $model = new CmsDiyformModel();
            $model->del($ids);
            $this->success(lang('operation success'));
        } else {
            $this->error(lang('delete fail'));

        }
    }

    public function state()
    {
        $id = $this->request->post('id');
        if ($id) {
            $where['id'] = $id;

            $link = CmsDiyformModel::find($id);
            $where['status'] = $link['status'] ? 0 : 1;
            CmsDiyformModel::update($where);

            $this->success(lang('operation success'));

        } else {
            $this->error(lang('edit fail'));

        }


    }

    public function field(){

        return view();
    }

    public function datalist(){


        return view();
    }
}