<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 南博插件
 *
 * @package Nabo
 * @author 南博工作室
 * @version 4.0
 * @link https://github.com/krait-team/Nabo-typecho
 */
class Nabo_Plugin implements Typecho_Plugin_Interface
{
    const MANIFEST = [
        'engineName' => 'typecho',
        'versionCode' => 20,
        'versionName' => '4.0'
    ];
    const ROUTE_SERVICE = "/nabo/service",
        ROUTE_USER = "/nabo/user", ROUTE_UPLOAD = "/nabo/upload";

    /**
     *
     * @access public
     * @return void
     */
    public static function begin()
    {
        include_once 'Kat/Kat.php';
    }

    /**
     *
     * @access public
     * @return void
     */
    public static function navBar()
    {
        ?>
        <a href="<?php Helper::options()->adminUrl('extending.php?panel=Nabo%2FManage.php'); ?>">南博</a>
        <?php
    }

    /**
     * 激活插件
     *
     * @return string|void
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        if (basename(dirname(__FILE__)) != 'Nabo') {
            throw new Typecho_Plugin_Exception(_t('插件目录名必须为 Nabo'));
        }

        if (isset(Helper::options()->plugins['activated']['Aidnabo'])) {
            throw new Typecho_Plugin_Exception(_t('Nabo插件和Aidnabo插件不可共存, 请禁用后再删除Aidnabo插件'));
        }

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $adapterName = $db->getAdapterName();

        if (strpos($adapterName, 'Mysql') !== false) {
            if (!$db->fetchRow($db->query("SHOW TABLES LIKE '{$prefix}nabo';", Typecho_Db::READ))) {
                $charset = $db->getConfig()['charset'] ?: 'utf8';
                $db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'nabo` (
		            `uid` INT(11) UNSIGNED NOT NULL,
		            `uin` INT(10) UNSIGNED DEFAULT 0,
		            `token` VARCHAR(36) DEFAULT NULL,
		            `markdown` INT(1) UNSIGNED DEFAULT 1,
		            `certKey` text,
		            `pushKey` VARCHAR(36) DEFAULT NULL,
		            `allowCert` INT(1) UNSIGNED DEFAULT 0,
		            `allowPush` INT(1) UNSIGNED DEFAULT 0,
		            PRIMARY KEY (`uid`),
		            KEY `uin` (`uin`)
		        ) DEFAULT CHARSET=' . $charset);
            }
        } else if (strpos($adapterName, 'SQLite') !== false) {
            if (!$db->fetchRow($db->query("SELECT name FROM sqlite_master WHERE TYPE='table' AND name='{$prefix}nabo';", Typecho_Db::READ))) {
                $db->query('CREATE TABLE ' . $prefix . 'nabo ( 
                    "uid" INTEGER NOT NULL PRIMARY KEY, 
		            "uin" INTEGER DEFAULT 0,
		            "token" VARCHAR(36) DEFAULT NULL,
		            "markdown" INT(1) DEFAULT 1,
		            "certKey" text,
		            "pushKey" VARCHAR(36) DEFAULT NULL,
		            "allowCert" INT(1) DEFAULT 0,
		            "allowPush" INT(1) DEFAULT 0);
                    CREATE UNIQUE INDEX ' . $prefix . 'nabo_uin ON ' . $prefix . 'nabo ("uin");');
            }
        } else {
            throw new Typecho_Plugin_Exception(_t('你的适配器为%s，目前只支持Mysql和SQLite', $adapterName));
        }

        Typecho_Plugin::factory('index.php')->begin = ['Nabo_Plugin', 'begin'];
        Typecho_Plugin::factory('admin/menu.php')->navBar = ['Nabo_Plugin', 'navBar'];
        Typecho_Plugin::factory('Widget_User')->loginSucceed = ['Nabo_Message', 'loginSucceed'];
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = ['Nabo_Message', 'finishComment'];

        Helper::addAction('nabo', 'Nabo_Action');
        Helper::addRoute("nabo_user", Nabo_Plugin::ROUTE_USER, "Nabo_User", 'action');
        Helper::addRoute("nabo_service", Nabo_Plugin::ROUTE_SERVICE, "Nabo_Service", 'action');
        Helper::addRoute("nabo_upload", Nabo_Plugin::ROUTE_UPLOAD, "Nabo_Upload", 'action');
        Helper::addPanel(4, 'Nabo/Manage.php', '南博插件', '南博', 'subscriber');

        return _t('南博插件 已启用');
    }

    /**
     * 禁用插件
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public static function deactivate()
    {
        if (Helper::options()->plugin('Nabo')->allowDrop) {
            $db = Typecho_Db::get();
            $db->query("DROP TABLE `{$db->getPrefix()}nabo`", Typecho_Db::WRITE);
        }

        Helper::removeAction('nabo');
        Helper::removeRoute("nabo_user");
        Helper::removeRoute("nabo_service");
        Helper::removeRoute("nabo_upload");
        Helper::removePanel(4, 'Nabo/Manage.php');

        return _t('南博插件 已被禁用');
    }

    /**
     * config
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allow', array(
            '0' => '关闭',
            '1' => '打开',
        ), '1', '南博接口', '这里是控制南博KAT-RPC接口, 关闭后南博不再有能力管理博客');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowHintLog', array(
            '0' => '关闭',
            '1' => '打开',
        ), '1', '账户登录通知', '开启后, 若有人登录博客后台则通过南博APP及时提醒博主(已配置好正确的推送密匙和打开推送情况下才通知)');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowMultivia', array(
            '0' => '关闭',
            '1' => '打开',
        ), '0', '密钥密码共存', '关闭后若开启了密钥登录, 就只能用密钥登录. 开启后, 既可以使用密钥登录(扫码), 也可以使用密码登录');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowSleep', array(
            '0' => '关闭',
            '1' => '打开',
        ), '1', '密匙错误休眠', '开启后, 在南博KAT-RPC通道里, 若密码或密钥不正确, 将会休眠6秒以防止穷举爆破, 即整个过程延时6秒');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowPush', array(
            '0' => '关闭',
            '1' => '打开',
        ), '1', '推送密匙更新', '由于随着南博推送服务的升级, 推送密匙可能发生变化. 开启后, 如果最新的推送密匙与博客配置的不同将<strong>自动更新密匙并开启推送</strong>');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowTheme', array(
            '0' => '禁止',
            '1' => '允许',
        ), '0', '主题设置能力', '开启后，在南博可以切换主题和配置主题');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowPlugin', array(
            '0' => '禁止',
            '1' => '允许',
        ), '0', '插件设置能力', '开启后，在南博启用和禁用插件以及配置插件');
        $form->addInput($enable);

        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowSetting', array(
            '0' => '禁止',
            '1' => '允许',
        ), '0', '基本设置能力', '开启后，在南博进行基本设置、评论设置、阅读设置、永久链接设置、以及个人资料设置');
        $form->addInput($enable);

        $drop = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowDrop', array(
            '0' => '不删除',
            '1' => '删除',
        ), '0', '删数据表', '请选择是否在禁用插件时，删除南博用户数据表，这张表是对应每个账户的个人配置，此表是本插件创建的。如果选择不删除，那么禁用后再次启用还是之前的用户数据就不用重新个人配置');
        $form->addInput($drop);
    }

    /**
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {

    }

    /**
     * @param bool $padding
     * @return Typecho_Widget_Helper_Form
     * @throws Typecho_Db_Exception
     * @throws Typecho_Exception
     */
    public static function profile($padding = true)
    {
        Typecho_Widget::widget('Widget_Options')->to($options);
        Typecho_Widget::widget('Widget_User')->to($user);
        $user->pass('subscriber');

        $form = new Typecho_Widget_Helper_Form(Typecho_Common::url('/action/nabo', Helper::options()->index),
            Typecho_Widget_Helper_Form::POST_METHOD);
        $form->setAttribute('enctype', 'multipart/form-data');

        // temporary
        $temporary = [];
        $keyDisplay = '';

        // padding
        if ($padding) {
            $db = Typecho_Db::get();
            $temporary = $db->fetchRow($db->select()
                ->from('table.nabo')
                ->where('uid = ?', $user->uid));
            if (empty($temporary)) {
                $db->query($db->insert('table.nabo')->rows(['uid' => $user->uid]));
                $temporary = $db->fetchRow($db->select()
                    ->from('table.nabo')
                    ->where("uid = ?", $user->uid));
            }

            if ($certKey = $temporary['certKey']) {
                $keyDisplay = "<strong>[已存在密钥]</strong><br>";
                $keyDisplay .= "密钥指纹: " . md5($certKey) . "<br>";
                $keyDisplay .= "<br>";
            }
        }

        $uin = new Typecho_Widget_Helper_Form_Element_Text(
            'uin', null, NULL,
            '南博号', '南博号, 应用于密钥登录与消息推送等等用处<br> 它在南博平台是唯一的，你可以在南博APP里查看');
        $form->addInput($uin);
        $uin->input->setAttribute('class', 'mono w-35');

        $submitKey = new Typecho_Widget_Helper_Form_Element_Submit();
        $submitKey->input->setAttribute('class', 'btn');
        $submitKey->input->setAttribute('type', 'button');
        $submitKey->input->setAttribute('onclick', "javascrtpt:window.open('" . Nabo_User::bindUrl() . "')");
        $form->addItem($submitKey);
        $submitKey->value(_t('扫码登录'));

        $allowCert = new Typecho_Widget_Helper_Form_Element_Radio('allowCert',
            array('0' => '关闭', '1' => '打开')
            , '0', '密钥登录', $keyDisplay . '<strong>若需开启或改绑, 点击扫码登录</strong>  南博与博客绑定后, 自动开启密钥登录<br>生成的二维码半分钟内有时效性, 仅与当前登录账户进行绑定, 并自动更新南博号和密钥');
        $form->addInput($allowCert);

        $markdown = new Typecho_Widget_Helper_Form_Element_Radio('markdown',
            array('0' => _t('关闭'), '1' => _t('打开')),
            '1', _t('南博接口中使用 Markdown 语法'),
            _t('对于完全支持 <a href="http://daringfireball.net/projects/markdown/">Markdown</a> 语法写作的离线编辑器, 打开此选项后将避免内容被转换为 HTML.'));
        $form->addInput($markdown);

        /** 撰写设置 */
        $_markdown = new Typecho_Widget_Helper_Form_Element_Radio('_markdown',
            array('0' => _t('关闭'), '1' => _t('打开')),
            $options->markdown, _t('使用 Markdown 语法编辑和解析内容'),
            _t('使用 <a href="http://daringfireball.net/projects/markdown/">Markdown</a> 语法能够使您的撰写过程更加简便直观.')
            . '<br />' . _t('此功能开启不会影响以前没有使用 Markdown 语法编辑的内容.'));
        $form->addInput($_markdown);

        $pushKey = new Typecho_Widget_Helper_Form_Element_Password(
            'pushKey', null, NULL,
            '推送密匙', '消息推送密匙，用于消息推送到南博，评论回复通知、自定义消息推送。(开启推送密匙更新后自动配置和更新推送密匙)');
        $form->addInput($pushKey);

        $allowPush = new Typecho_Widget_Helper_Form_Element_Radio(
            'allowPush', array(
            '0' => '关闭',
            '1' => '打开',
        ), '0', '推送开关', '打开后同时填写上面的消息推送密匙，用于消息推送到南博，一般延迟在5分钟，请勿频繁推送，否者将无法再使用');
        $form->addInput($allowPush);

        $submit = new Typecho_Widget_Helper_Form_Element_Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        if ($padding) {
            $uin->value($temporary['uin']);
            $markdown->value($temporary['markdown']);
            $pushKey->value($temporary['pushKey']);
            $allowCert->value($temporary['allowCert']);
            $allowPush->value($temporary['allowPush']);
        }

        $do = new Typecho_Widget_Helper_Form_Element_Hidden('do');
        $form->addInput($do);

        $do->value('update-profile');
        $submit->value(_t('更新我的档案'));

        $uin->addRule('xssCheck', _t('请勿输入唯一标识'));
        return $form;
    }
}
