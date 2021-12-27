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
     * @target launch
     */
    public function action()
    {
        if (!$this->request->is('uin&name&token')) {
            $this->response('缺少必要参数');
        }

        $liteuser = Typecho_Widget::widget('Nabo_User');
        $liteuser->identity([
            'uin' => $this->request->get('uin'),
            'name' => $this->request->get('name'),
            'token' => $this->request->get('token')
        ]);

        try {
            $liteuser->register();
            $this->user->execute();
        } catch (Exception $e) {
            $this->response(
                $e->getMessage()
            );
        }

        // check able
        if (!$this->user->pass('contributor', true)) {
            $this->response('权限不足');
        }

        try {
            if ($this->request->is('do=modify&cid')) {
                $this->modify();
            } else {
                $this->upload();
            }
        } catch (Exception $e) {
            $this->response(
                $e->getMessage()
            );
        }
    }

    /**
     * @target upload
     */
    public function upload()
    {
        if (empty($_FILES)) {
            $this->response('不存在文件');
        }

        // select
        $file = array_pop($_FILES);
        if ($file['error'] != 0 || !is_uploaded_file($file['tmp_name'])) {
            $this->response('文件异常');
        }

        // upload
        if (empty($result = parent::uploadHandle($file))) {
            $this->response('上传失败');
        }

        // insert
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

        $this->response(
            '上传成功', 1,
            Nabo_Format::media($media)
        );
    }

    /**
     * @target modify
     */
    public function modify()
    {
        $this->response(
            '暂时不支持更新文件'
        );
    }

    /**
     * @param false $run
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
     * @param $msg
     * @param int $code
     * @param null $data
     */
    public function response($msg, $code = 0, $data = null)
    {
        kat_response([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]);
    }
}
