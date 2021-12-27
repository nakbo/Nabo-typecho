<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 行动组件
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
 */
class Nabo_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * @var Typecho_Db
     */
    protected $db;

    /**
     * @var Widget_User
     */
    protected $user;

    /**
     * @var Widget_Options
     */
    protected $options;

    /**
     * @param $options
     * @throws Typecho_Exception
     */
    public function updateOption($options)
    {
        foreach ($options as $name => $value) {
            if ($this->db->fetchObject($this->db->select(array('COUNT(*)' => 'num'))
                    ->from('table.options')->where('name = ? AND user = ?', $name, $this->user->uid))->num > 0) {
                $this->widget('Widget_Abstract_Options')
                    ->update(array('value' => $value), $this->db->sql()->where('name = ? AND user = ?', $name, $this->user->uid));
            } else {
                $this->widget('Widget_Abstract_Options')->insert(array(
                    'name' => $name,
                    'value' => $value,
                    'user' => $this->user->uid
                ));
            }
        }
    }

    /**
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public function updateProfile()
    {
        $this->user->pass('subscriber');

        if (Nabo_Plugin::profile(false)->validate()) {
            $this->response->goBack();
        }

        // temporary
        $temporary = $this->request->from('uin', 'markdown', 'pushKey', 'allowPush', 'allowCert', '_markdown');
        $this->updateOption([
            'markdown' => $temporary['_markdown'] ? 1 : 0
        ]);
        unset($temporary['_markdown']);

        if (empty($temporary['uin'])) {
            $error = true;
        } else {
            if ($temporary['uin'] < 100001) {
                $this->throwRedirect('南博号不正确');
            }
            $error = false;
        }

        if ($temporary['pushSafe'] == 1 && (empty($temporary['pushKey']) || strlen($temporary['pushKey']) < 32)) {
            $this->throwRedirect('推送密匙不正确, 无法开启推送');
        }

        if ($temporary['allowCert'] == 1) {
            if ($error) {
                $this->throwRedirect('南博号不正确, 无法开启密匙登录');
            }
            $first = $this->db->fetchRow($this->db->select()
                ->from('table.nabo')
                ->where('uid = ?', $this->user->uid));
            if (empty($first['certKey'])) {
                $this->throwRedirect('未进行扫码绑定, 无法开启密匙登录');
            }
        }

        $this->db->query($this->db->update('table.nabo')
            ->rows($temporary)
            ->where('uid = ?', $this->user->uid));

        $this->throwRedirect('南博用户基本信息已经被更新', 'success');
    }

    /**
     * @param $notice
     * @param string $typeFix
     * @throws Typecho_Exception
     */
    private function throwRedirect($notice, $typeFix = 'error')
    {
        $this->widget('Widget_Notice')->set(_t($notice), 'notice', $typeFix);
        $this->response->redirect(Typecho_Common::url('extending.php?panel=Nabo%2FManage.php', $this->options->adminUrl));
    }

    /**
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public function action()
    {
        $this->db = Typecho_Db::get();
        Typecho_Widget::widget('Widget_User')->to($this->user);
        $this->options = Typecho_Widget::widget('Widget_Options');
        $this->on($this->request->is('do=update-profile'))->updateProfile();
    }
}