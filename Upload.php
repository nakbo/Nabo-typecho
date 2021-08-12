<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 上传组件
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
 */
class Nabo_Upload extends Widget_Upload implements Widget_Interface_Do
{
    /**
     * 上传
     *
     * @access public
     * @return void
     */
    public function upload()
    {
        if (empty($_FILES)) {
            kat_response(['code' => 0, 'msg' => '不存在文件']);
        }

        // select
        $file = array_pop($_FILES);
        if ($file['error'] != 0 || !is_uploaded_file($file['tmp_name'])) {
            kat_response(['code' => 0, 'msg' => '文件异常']);
        }

        // upload
        if (($result = parent::uploadHandle($file)) === false) {
            kat_response(['code' => 0, 'msg' => '上传失败']);
        }

        $id = $this->insert(array(
            'title' => $result['name'],
            'slug' => $result['name'],
            'type' => 'attachment',
            'status' => 'publish',
            'text' => serialize($result),
            'allowComment' => 1,
            'allowPing' => 0,
            'allowFeed' => 1
        ));

        // fetch
        $media = $this->db->fetchRow($this->select()
            ->where('table.contents.cid = ?', $id)
            ->where('table.contents.type = ?', 'attachment'));

        // push
        $this->push($media);

        // hook
        $this->pluginHandle('Widget_Upload')->upload($this);

        kat_response(['code' => 1, 'msg' => '上传成功',
            'data' => Nabo_Format::mediaOf($media)
        ]);
    }

    /**
     * 更新
     *
     * @access public
     * @return void
     */
    public function modify()
    {
        $this->response->throwJson(['code' => 0, 'msg' => '暂时不支持更新文件']);
    }

    /**
     * 重载
     *
     * @access public
     * @param boolean $run 是否执行
     * @return void
     */
    public function execute($run = false)
    {
        if ($run) {
            parent::execute();
        }
        // 临时保护模块
        $this->security->enable(false);
    }

    /**
     * 初始化函数
     *
     * @access public
     * @return void
     * @throws Typecho_Widget_Exception
     * @throws Typecho_Exception
     */
    public function action()
    {
        if (!$this->request->is("uin&token")) {
            kat_response(['code' => 0, 'msg' => '缺少必要参数']);
        }

        Typecho_Widget::widget('Nabo_User')->to($liteuser);
        $liteuser->identity([
            'uin' => $this->request->get('uin'),
            'token' => $this->request->get('token')
        ]);

        try {
            $liteuser->challenge();
            $this->user->execute();
        } catch (Exception $e) {
            kat_response(['code' => 0, 'msg' => $e->getMessage()]);
        }

        if (!$this->user->pass('contributor', true)) {
            kat_response(['code' => 0, 'msg' => '权限不足']);
        }

        if ($this->request->is('do=modify&cid')) {
            $this->modify();
        } else {
            $this->upload();
        }
    }
}
