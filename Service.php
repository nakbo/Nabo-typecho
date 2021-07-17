<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 南博 KAT-RPC 接口
 *
 * @Author 陆之岇(Kraity)
 * @Team Krait Dev Team
 * @GitHub https://github.com/kraity
 */
class Nabo_Service extends Widget_Abstract_Contents implements Widget_Interface_Do
{
    /**
     * @var Kat_Server
     */
    protected $server;

    /**
     * @var Typecho_Config
     */
    protected $option;

    /**
     * @var Nabo_User
     */
    protected $liteuser;

    /**
     * 已经使用过的组件列表
     *
     * @access private
     * @var array
     */
    protected $_usedWidgetNameList = [];

    /**
     * @param false $run
     */
    public function execute($run = false)
    {
        if ($run) {
            parent::execute();
        }
        // security
        $this->security->enable(false);
    }

    /**
     * 行动
     *
     * @access public
     * @return void
     * @throws Exception
     */
    public function action()
    {
        // report
        error_reporting(E_ERROR);

        // config
        $this->option = $this->options->plugin('Nabo');

        // switch
        if (empty($this->option->allow)) {
            throw new Typecho_Widget_Exception(_t('请求的地址不存在'), 404);
        }

        // user
        Typecho_Widget::widget('Nabo_User')->to($this->liteuser);

        include_once 'Kat/Kat.php';
        include_once 'Kat/Server.php';

        // server
        $this->server = new Kat_Server();

        // register
        $this->server->register($this);

        // launch
        $this->server->launch();
    }

    /**
     * @param $alias
     * @param null $params
     * @param null $request
     * @param false $enableResponse
     * @return Typecho_Widget
     * @throws Typecho_Exception
     */
    private function singletonWidget($alias, $params = NULL, $request = NULL, $enableResponse = false)
    {
        $this->_usedWidgetNameList[] = $alias;
        $widget = Typecho_Widget::widget($alias, $params, $request, $enableResponse);
        if ($enableResponse) {
            $widget->response = new Nabo_Helper();
        }
        return $widget;
    }

    /**
     * 回收变量
     *
     * @access public
     * @param $method
     * @param $kat
     * @return void
     */
    public function hooker_after($method, &$kat)
    {
        foreach ($this->_usedWidgetNameList as $key => $widgetName) {
            $this->destory($widgetName);
            unset($this->_usedWidgetNameList[$key]);
        }
    }

    /**
     * @param $method
     * @param $auth
     * @throws Exception
     */
    public function cipher_access($method, $auth)
    {
        // identity
        $this->liteuser->identity($auth);

        // check
        if (in_array($method, ['kat_user_login', 'kat_user__login'])) {
            return;
        }

        // challenge
        $this->liteuser->challenge();

        // modify
        $this->user->execute();
    }

    /**
     * @param $cert
     * @param $request
     * @throws Exception
     */
    public function cipher_accept($cert, $request)
    {
        // check
        if ($cert['digest'] != md5($request)) {
            throw new Exception('非法请求', 102);
        }
    }

    /**
     * @param $method
     * @param $callback
     */
    public function cipher_challenge($method, &$callback)
    {
        $callback['cert'] = [
            'digest' => md5($callback['response'])
        ];
    }

    /**
     * 验证权限
     *
     * @param string $level
     * @throws Exception
     */
    public function checkAccess($level = 'contributor')
    {
        if (!$this->user->pass($level, true)) {
            throw new Exception('权限不足', 101);
        }
    }

    /**
     * 版本
     *
     * @param $data
     * @return array
     */
    public function method_kat_version($data)
    {
        return Nabo_Plugin::MANIFEST;
    }

    /**
     * 登录
     *
     * @access public
     * @param $credential
     * @return array|Exception
     */
    public function metcip_kat_user_login($credential)
    {
        return $this->liteuser->login($credential);
    }

    /**
     * 登录
     *
     * @access public
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_user__login($data)
    {
        return $this->liteuser->_login($data);
    }

    /**
     * 用户
     *
     * @access public
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_user_sync($data)
    {
        $this->checkAccess();

        // touch
        $touch = (int)$data['touch'];

        // callback
        $callback = array(
            'region' => 'sync'
        );

        // user
        $user = $this->liteuser->user();

        // push key
        if ($this->option->allowPush) {
            if (strlen($data['pushKey']) > 31 && $user['pushKey'] != $data['pushKey']) {
                $this->db->query($this->db
                    ->update('table.nabo')
                    ->rows([
                        'pushKey' => $data['pushKey'],
                        'allowPush' => 1
                    ])->where('uid = ?', $user['uid']));
            }
        }

        if ($touch) {
            $callback['user'] = $this->liteuser->respond($user);
        }

        return $callback;
    }

    /**
     * 统计
     *
     * @param $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_stat_sync($data)
    {
        $this->checkAccess();

        $post = $this->db->fetchRow($this->db->select([
            "COUNT(cid)" => 'all',
            "SUM(IF(status = 'publish' AND type = 'post', 1, 0))" => 'publish',
            "SUM(IF(status = 'private' AND type = 'post', 1, 0))" => 'private',
            "SUM(IF(status = 'waiting', 1, 0))" => 'waiting',
            "SUM(IF(status = 'hidden' AND type = 'post', 1, 0))" => 'hidden',
            "SUM(IF(type = 'post_draft', 1, 0))" => 'draft'
        ])->from('table.contents')->where('type LIKE ?', 'post%')
            ->where('authorId = ?', $this->liteuser->uid()));
        $post['all'] -= $post['draft'];
        $post['textSize'] = Nabo_Format::wordsSizeOf(
            $this->liteuser->uid(), 'table.contents', 'post'
        );
        $post = array_map(function ($size) {
            return (int)$size;
        }, $post);

        $page = $this->db->fetchRow($this->db->select([
            "COUNT(cid)" => 'all',
            "SUM(IF(status = 'publish' AND type = 'page', 1, 0))" => 'publish',
            "SUM(IF(status = 'hidden' AND type = 'page', 1, 0))" => 'hidden',
            "SUM(IF(type = 'page_draft', 1, 0))" => 'draft'
        ])->from('table.contents')->where('type LIKE ?', 'page%')
            ->where('authorId = ?', $this->liteuser->uid()));
        $page['all'] -= $page['draft'];
        $page['textSize'] = Nabo_Format::wordsSizeOf(
            $this->liteuser->uid(), 'table.contents', 'page'
        );
        $page = array_map(function ($size) {
            return (int)$size;
        }, $page);

        $comment = $this->db->fetchRow($this->db->select([
            "COUNT(coid)" => 'all',
            "SUM(IF(authorId = '{$this->liteuser->uid()}', 1,0))" => 'me',
            "SUM(IF(status = 'approved', 1, 0))" => 'publish',
            "SUM(IF(status = 'waiting', 1, 0))" => 'waiting',
            "SUM(IF(status = 'spam', 1, 0))" => 'spam'
        ])->from('table.comments')
            ->where('ownerId = ?', $this->liteuser->uid()));
        $comment['textSize'] = Nabo_Format::wordsSizeOf(
            $this->liteuser->uid(), 'table.comments', 'comment'
        );
        $comment = array_map(function ($size) {
            return (int)$size;
        }, $comment);

        $meta = $this->db->fetchRow($this->db->select([
            "SUM(IF(type = 'category', 1,0))" => 'category_all',
            "SUM(IF(type = 'category' AND count > 0, 1,0))" => 'category_archive',
            "SUM(IF(type = 'tag', 1, 0))" => 'tag_all',
            "SUM(IF(type = 'tag' AND count > 0, 1,0))" => 'tag_archive'
        ])->from('table.metas'));
        $meta = array_map(function ($size) {
            return (int)$size;
        }, $meta);

        $category = [
            'all' => $meta['category_all'],
            'archive' => $meta['category_archive']
        ];

        $tag = [
            'all' => $meta['tag_all'],
            'archive' => $meta['tag_archive']
        ];

        $media = $this->db->fetchRow($this->db->select([
            "COUNT(cid)" => 'all',
            "SUM(IF(parent > 0, 1,0))" => 'archive'
        ])->from('table.contents')->where('type = ?', 'attachment'));
        $media = array_map(function ($size) {
            return (int)$size;
        }, $media);

        return compact('post', 'page', 'comment',
            'category', 'tag', 'media'
        );
    }

    /**
     * 笔记
     *
     * @param array $data
     * @param string $type
     * @return array|Exception
     * @throws Typecho_Widget_Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_note_list($data, $type = 'post')
    {
        // check
        if ($type == 'post') {
            $this->checkAccess();
        } else if ($type == 'page') {
            $this->checkAccess('editor');
        } else {
            return new Exception("异常请求");
        }

        // select
        $select = $this->db->select()->from('table.contents');

        // meta
        if (is_numeric($data['meta'])) {
            $select->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->where('table.relationships.mid = ?', $data['meta']);
        }

        // total
        if ($data['total'] != 'on' || !$this->user->pass('editor', true)) {
            $select->where('table.contents.authorId = ?', $this->liteuser->uid());
        }

        // status
        switch ($status = $data['status'] ?: 'total') {
            case 'total':
                $select->where('table.contents.type = ?', $type);
                break;
            case 'draft':
                $select->where('table.contents.type LIKE ?', '%_draft');
                break;
            default:
                $select->where('table.contents.type = ?', $type);
                $select->where('table.contents.status = ?', $status);
        }

        // search
        if (isset($data['keywords'])) {
            $searchQuery = Nabo_Format::searchOf($data['keywords']);
            $select->where('table.contents.title LIKE ? OR table.contents.text LIKE ?', $searchQuery, $searchQuery);
        }

        // order
        if ($type == 'page') {
            $select->order('table.contents.order');
        } else {
            $select->order('table.contents.' . ($status == 'draft' ? 'modified' : 'created'), Typecho_Db::SORT_DESC);
        }

        // paging
        Nabo_Format::pagingOf($data, $page, $size);
        $select->page($page, $size);

        return Nabo_Format::notesOf(
            $this, $this->db->fetchAll($select)
        );
    }

    /**
     * 同步笔记
     *
     * @param $nid
     * @param string $type
     * @return array|Exception
     * @throws Typecho_Widget_Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_note_sync($nid, $type = 'post')
    {
        // check
        if ($type == 'post') {
            $this->checkAccess();
        } else if ($type == 'page') {
            $this->checkAccess('editor');
        } else {
            return new Exception("异常请求");
        }

        if (count($row = $this->db->fetchRow($this->db->select()
            ->from('table.contents')
            ->where('authorId = ?', $this->liteuser->uid())
            ->where('cid = ?', $nid)))) {
            return Nabo_Format::noteOf(
                $this->filter($row)
            );
        }
        return new Exception('不存在此文章', 403);
    }

    /**
     * 创建笔记
     *
     * @param array $data
     * @param string $type
     * @return array|Exception
     * @throws ReflectionException
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_note_create($data, $type = 'post')
    {
        // check
        if ($type == 'post') {
            $this->checkAccess();
        } else if ($type == 'page') {
            $this->checkAccess('editor');
        } else {
            return new Exception("异常请求");
        }

        // do
        $do = ($draft = $data['status'] == 'draft') ? 'save' : 'publish';

        // widget
        $editor = $this->singletonWidget('Widget_Contents_Post_Edit');

        // push
        if (!empty($data['nid'])) {
            if ($temporary = $this->db->fetchRow($editor->select()
                ->where('cid = ? and type LIKE ?', $data['nid'], $type . '%'))) {
                if ($temporary['authorId'] != $this->liteuser->uid() ||
                    !$this->user->pass('editor', true)) {
                    return new Exception('没有编辑权限');
                }
                if ($temporary['parent']) {
                    $temporary['draft']['cid'] = $temporary[$draft ? 'cid' : 'parent'];
                }
                $editor->push($temporary);
            } else {
                return new Exception('文章不存在, 无法编辑');
            }
        }

        // draft
        if ($draft) {
            $type .= '_draft';
        }

        // note
        $note = array(
            'type' => $type,
            'text' => $data['content'],
        );

        // markdown
        if ($this->user->markdown) {
            $note['text'] = '<!--markdown-->' . $note['text'];
        }

        // slug
        if (isset($data['slug'])) {
            $note['slug'] = $data['slug'];
        }

        // filed
        foreach (['title', 'template', 'order', 'password',
                     'allowPing', 'allowFeed', 'allowComment'] as $filed) {
            if (isset($data[$filed])) {
                $note[$filed] = $data[$filed];
            }
        }
        unset($filed);

        // fields
        if (is_array($data['fields'])) {
            foreach ($data['fields'] as $field) {
                $note['fields'][$field['key']] = [
                    $field['type'], $field['val']
                ];
            }
        }

        // categories
        if (is_array($data['categories'])) {
            foreach ($data['categories'] as $category) {
                if (empty($_category = $this->db->fetchRow($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category)))) {
                    $_category = $this->metcip_kat_category_create(['name' => $category]);
                }
                $note['category'][] = $_category['mid'];
            }
        } else {
            $note['category'] = [];
        }

        // tags
        if (is_array($data['tags'])) {
            $note['tags'] = implode(',', $data['tags']);
        }

        // attachment
        foreach ($this->db->fetchAll($this->select()
            ->where('table.contents.type = ? AND (table.contents.parent = 0 OR table.contents.parent IS NULL)',
                'attachment'), [$this, 'filter']) as $attach) {
            if (strpos($note['text'], $attach['attachment']->url) !== false) {
                $note['attachment'][] = $attach['cid'];
            }
        }

        // request
        $editor->request->setParams($note);

        // publish
        $save = (new ReflectionClass($editor))->getMethod($do);
        $save->setAccessible(true);
        $save->invoke($editor, $note);

        return array(
            'type' => $type,
            'nid' => intval($editor->cid)
        );
    }

    /**
     * 更新笔记
     *
     * @param array $data
     * @param string $type
     * @return array|Exception
     * @throws ReflectionException
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function metcip_kat_note_modify($data, $type = 'post')
    {
        return $this->metcip_kat_note_create($data, $type);
    }

    /**
     * 删除笔记
     *
     * @param $nid
     * @param string $type
     * @return boolean|Exception
     * @throws Typecho_Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_note_remove($nid, $type = 'post')
    {
        // type
        if ($type == 'page') {
            $this->checkAccess('editor');

            $this->singletonWidget(
                'Widget_Contents_Page_Edit', NULL, array('cid' => $nid)
            )->deletePage();
        } else {
            $this->checkAccess();

            $this->singletonWidget(
                'Widget_Contents_Post_Edit', NULL, array('cid' => $nid)
            )->deletePost();
        }

        return Nabo_Format::successOf();
    }

    /**
     * 笔记自定义字段
     *
     * @param $data
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_note_field($data)
    {
        $this->checkAccess();

        // widget
        $widget = $this->singletonWidget('Widget_Contents_Post_Edit');

        // buffer
        ob_start();

        $layout = new Typecho_Widget_Helper_Layout();
        $widget->pluginHandle('Widget_Contents_Post_Edit')->getDefaultFieldItems($layout);

        if (file_exists($themeFile = $this->options->themeFile($this->options->theme, 'functions.php'))) {
            require_once $themeFile;

            if (function_exists('themeFields')) {
                themeFields($layout);
            }

            if (function_exists($widget->themeCustomFieldsHook)) {
                call_user_func($widget->themeCustomFieldsHook, $layout);
            }
        }
        $layout->render();

        // buffer
        return ob_get_clean();
    }

    /**
     * 评论
     *
     * @access public
     * @param array $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_discuss_list($data)
    {
        $this->checkAccess();

        // select
        $select = $this->db->select('table.comments.*', 'table.contents.title')->from('table.comments')
            ->join('table.contents', 'table.comments.cid = table.contents.cid', Typecho_Db::LEFT_JOIN);

        // total
        if ($data['total'] != 'on' || !$this->user->pass('editor', true)) {
            $select->where('table.comments.ownerId = ?', $this->liteuser->uid());
        }

        // nid
        if (isset($data['nid'])) {
            $select->where('table.comments.cid = ?', $data['nid']);
        }

        // mail
        if (isset($data['mail'])) {
            $select->where('table.comments.mail = ?', $data['mail']);
        }

        // status
        if (isset($data['status'])) {
            $select->where('table.comments.status = ?', $data['status']);
        }

        // search
        if (isset($struct['keywords'])) {
            $searchQuery = Nabo_Format::searchOf($struct['keywords']);
            $select->where('table.comments.author LIKE ? OR table.comments.text LIKE ? OR table.comments.url LIKE ?',
                $searchQuery, $searchQuery, $searchQuery
            );
        }

        // paging
        Nabo_Format::pagingOf($data, $page, $size);

        // fetch
        $comments = $this->db->fetchAll(
            $select->order('table.comments.created', Typecho_Db::SORT_DESC)->page($page, $size)
        );

        // thread
//        if ($data['thread'] == 'on') {
//            $thread = array();
//
//            foreach ($comments as $comment) {
//                // parent
//                $parent = $comment['parent'];
//
//                // check
//                if (isset($thread[$parent])) {

        // TODO: comment's thread

//                } else {
//                    $thread[$parent] = &$comment;
//                }
//            }
//        }

        return Nabo_Format::discussesOf($comments);
    }

    /**
     * 同步评论
     *
     * @access public
     * @param $did
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_discuss_sync($did)
    {
        $this->checkAccess();

        // check
        if (empty($did)) {
            return new Exception('评论不存在', 404);
        }

        // select
        $select = $this->db->select('table.comments.*', 'table.contents.title')->from('table.comments')
            ->join('table.contents', 'table.comments.cid = table.contents.cid', Typecho_Db::LEFT_JOIN);

        // comment
        if (empty($comment = $this->db->fetchRow($select->where('coid = ?', $did)->limit(1)))) {
            return new Exception('评论不存在', 404);
        }

        // total
        if ($comment['ownerId'] != $this->liteuser->uid() || !$this->user->pass('editor', true)) {
            return new Exception('没有获取评论的权限', 403);
        }

        return Nabo_Format::discussOf($comment);
    }

    /**
     * 创建评论
     *
     * @access public
     * @param array $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_discuss_create($data)
    {
        $this->checkAccess();

        if (!isset($data['message'])) {
            return new Exception('评论内容为空', 404);
        }

        if (empty($data['nid']) || empty($row = $this->db->fetchRow($this->select()->where('cid = ?', $data['nid'])->limit(1)))) {
            return new Exception(_t('文章不存在'), 404);
        }

        // push
        $this->push($row);

        // allow
        if (!$this->allow('comment')) {
            return new Exception(_t('该文章不允许评论'), 403);
        }

        $request = array(
            'type' => 'comment',
            'text' => $data['message'],
            'parent' => $data['oid'],
        );

        // widget
        $editor = $this->singletonWidget(
            'Widget_Feedback', NULL, $request
        );

        // reflection
        $ref = new ReflectionClass($editor);
        $property_content = $ref->getProperty('_content');
        $property_content->setAccessible(true);
        $property_content->setValue($editor, $this);
        $comment = $ref->getMethod('comment');
        $comment->setAccessible(true);

        // comment
        try {
            $comment->invoke($editor);
        } catch (Exception $e) {
            return new Exception($e->getMessage());
        }

        return Nabo_Format::discussOf(
            end($editor->stack)
        );
    }

    /**
     * 编辑评论
     *
     * @access public
     * @param array $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_discuss_modify($data)
    {
        $this->checkAccess('editor');

        if (empty($data['did'])) {
            return new Exception("评论不存在");
        }

        $request = array(
            'coid' => $data['did']
        );

        if (isset($data['nickname'])) {
            $request['author'] = $data['nickname'];
        }

        if (isset($data['mail'])) {
            $request['mail'] = $data['mail'];
        }

        if (isset($data['site'])) {
            $request['url'] = $data['site'];
        }

        if (isset($data['message'])) {
            $request['text'] = $data['message'];
        }

        // widget
        $editor = $this->singletonWidget(
            'Widget_Comments_Edit', NULL, $request, true
        )->editComment();

        // extract
        $extract = $editor->response->extract('throwJson');

        if ($extract['success']) {
            return Nabo_Format::discussOf(
                $extract['comment']
            );
        }

        return new Exception($extract['message']);
    }

    /**
     * 标记评论
     *
     * @access public
     * @param $status
     * @param array $did
     * @return boolean|Exception
     * @throws ReflectionException
     * @throws Typecho_Exception
     * @throws Exception
     */
    public function metcip_kat_discuss_remark($did, $status)
    {
        $this->checkAccess('editor');

        if (!is_array($did)) {
            return new Exception("评论不存在");
        }

        if (empty($status)) {
            return new Exception("不必更新的情况");
        }

        $editor = $this->singletonWidget('Widget_Comments_Edit');
        $mark = (new ReflectionClass($editor))->getMethod('mark');
        $mark->setAccessible('mark');

        $updateRows = 0;
        foreach ($did as $id) {
            if ($mark->invoke($editor, $id, $status)) {
                $updateRows++;
            }
        }

        return $updateRows != 0;
    }

    /**
     * 删除评论
     *
     * @access public
     * @param int $did
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_discuss_remove($did)
    {
        $this->checkAccess('editor');

        if (!is_array($did)) {
            return new Exception('评论不存在', 404);
        }

        // widget
        $this->singletonWidget(
            'Widget_Comments_Edit', NULL, array('coid' => $did)
        )->deleteComment();

        return Nabo_Format::successOf();
    }

    /**
     * 分类
     *
     * @param $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_meta_list($data)
    {
        $this->checkAccess('editor');

        // fetch
        $metas = $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->where('type = ?', $data['type'] == 'tag' ? 'tag' : 'category')
            ->order('table.metas.order'));

        if (isset($data['filter'])) {
            $list = [];
            if (is_array($data['filter'])) {
                foreach ($metas as $meta) {
                    $m = [];
                    foreach ($data['filter'] as $filter) {
                        $m[$filter] = $meta[$filter];
                    }
                    $list[] = $m;
                }
            } else {
                foreach ($metas as $meta) {
                    $list[] = $meta[$data['filter']];
                }
            }
            return $list;
        }

        return Nabo_Format::metasOf($metas);
    }

    /**
     * 分类
     *
     * @param $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_category_list($data)
    {
        $data['type'] = 'category';
        return $this->metcip_kat_meta_list($data);
    }

    /**
     * 所有标签
     *
     * @param $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_tag_list($data)
    {
        $data['type'] = 'tag';
        return $this->metcip_kat_meta_list($data);
    }

    /**
     * 创建分类
     *
     * @param array $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_category_create($data)
    {
        $this->checkAccess('editor');

        $meta = array(
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent' => isset($data['_mid']) ? $data['_mid'] : 0,
            'description' => isset($data['desc']) ? $data['desc'] : $data['name']
        );

        // widget
        $editor = $this->singletonWidget(
            'Widget_Metas_Category_Edit', NULL, $meta
        )->insertCategory();

        return Nabo_Format::metaOf(
            end($editor->stack)
        );
    }

    /**
     * 创建标签
     *
     * @param array $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_tag_create($data)
    {
        $this->checkAccess('editor');

        $meta = array(
            'name' => $data['name'],
            'slug' => $data['slug']
        );

        // widget
        $editor = $this->singletonWidget(
            'Widget_Metas_Tag_Edit', NULL, $meta
        )->insertTag();

        return Nabo_Format::metaOf(
            end($editor->stack)
        );
    }

    /**
     * 编辑分类
     *
     * @param array $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_category_modify($data)
    {
        $this->checkAccess('editor');

        if (empty($data['mid'])) {
            return new Exception("请求错误");
        }

        // fetch
        if (empty($meta = $this->db->fetchRow(
            $this->db->select('mid')->from('table.metas')
                ->where('type = ? AND mid = ?', 'category', $data['mid'])))) {
            return new Exception('没有查找到分类', 404);
        }

        if (isset($data['name'])) {
            $meta['name'] = $data['name'];
        }

        if (isset($data['slug'])) {
            $meta['slug'] = $data['slug'];
        }

        if (isset($data['oid'])) {
            $meta['parent'] = $data['oid'];
        }

        if (isset($data['desc'])) {
            $meta['description'] = $data['desc'];
        }

        // widget
        $editor = $this->singletonWidget(
            'Widget_Metas_Category_Edit', NULL, $meta
        )->updateCategory();

        return Nabo_Format::metaOf(
            end($editor->stack)
        );
    }

    /**
     * 编辑标签
     *
     * @param array $data
     * @return array|Exception
     * @throws Exception
     * @access public
     */
    public function metcip_kat_tag_modify($data)
    {
        $this->checkAccess('editor');

        if (empty($data['mid'])) {
            return new Exception("请求错误");
        }

        // fetch
        if (empty($meta = $this->db->fetchRow(
            $this->db->select('mid')->from('table.metas')
                ->where('type = ? AND mid = ?', 'tag', $data['mid'])))) {
            return new Exception('没有查找到标签', 404);
        }

        if (isset($data['name'])) {
            $meta['name'] = $data['name'];
        }

        if (isset($data['slug'])) {
            $meta['slug'] = $data['slug'];
        }

        // widget
        $editor = $this->singletonWidget(
            'Widget_Metas_Tag_Edit', NULL, $meta
        )->updateTag();

        return Nabo_Format::metaOf(
            end($editor->stack)
        );
    }

    /**
     * 删除分类
     *
     * @access public
     * @param $mid
     * @return boolean|Exception
     * @throws Exception
     */
    public function metcip_kat_category_remove($mid)
    {
        $this->checkAccess('editor');

        if (!is_array($mid)) {
            return new Exception("请求错误");
        }

        $this->singletonWidget(
            'Widget_Metas_Category_Edit', NULL, array('mid' => $mid)
        )->deleteCategory();

        return Nabo_Format::successOf();
    }

    /**
     * 删除标签
     *
     * @access public
     * @param $mid
     * @return boolean|Exception
     * @throws Exception
     */
    public function metcip_kat_tag_remove($mid)
    {
        $this->checkAccess('editor');

        if (!is_array($mid)) {
            return new Exception("请求错误");
        }

        $this->singletonWidget(
            'Widget_Metas_Tag_Edit', NULL, array('mid' => $mid)
        )->deleteTag();

        return Nabo_Format::successOf();
    }

    /**
     * 分类或标签排序
     *
     * @access public
     * @param $type
     * @param $mid
     * @return boolean|Exception
     * @throws Typecho_Exception
     * @throws Exception
     */
    public function metcip_kat_meta_sort($type, $mid)
    {
        $this->checkAccess('editor');

        if (!is_array($mid) || !in_array($type, ['category', 'tag'])) {
            return new Exception("请求错误");
        }

        $this->singletonWidget(
            'Widget_Abstract_Metas'
        )->sort(array('mid' => $mid), $type);

        return true;
    }

    /**
     * 媒体文件
     *
     * @access public
     * @param array $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_media_list($data)
    {
        $this->checkAccess();

        // select
        $select = $this->select()->where('table.contents.type = ?', 'attachment');

        if (!$this->user->pass('editor', true)) {
            $select->where('table.contents.authorId = ?', $this->liteuser->uid());
        }

        // search
        if (isset($data['keywords'])) {
            $searchQuery = Nabo_Format::searchOf($data['keywords']);
            $select->where('table.contents.title LIKE ? OR table.contents.text LIKE ?',
                $searchQuery, $searchQuery
            );
        }

        Nabo_Format::pagingOf($data, $page, $size);

        // page
        $select->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->page($page, $size);

        return Nabo_Format::mediasOf(
            $this->db->fetchAll($select)
        );
    }

    /**
     * 删除附件
     *
     * @access public
     * @param array $mid
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_media_remove($mid)
    {
        $this->checkAccess();

        if (!is_array($mid)) {
            return new Exception("缺少必要参数");
        }

        // widget
        $this->singletonWidget(
            'Widget_Contents_Attachment_Edit', NULL, array('cid' => $mid)
        )->deleteAttachment();

        return Nabo_Format::successOf();
    }

    /**
     * 清理未归档的附件
     *
     * @access public
     * @param $data
     * @return boolean|Exception
     * @throws Exception
     */
    public function metcip_kat_media_clear($data)
    {
        $this->checkAccess('editor');

        $this->singletonWidget('Widget_Contents_Attachment_Edit')->clearAttachment();

        return Nabo_Format::successOf();
    }

    /**
     * 编辑附件
     *
     * @access public
     * @param array $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_media_modify($data)
    {
        $this->checkAccess();

        if (empty($data['mid']) || empty($data['name'])) {
            return new Exception("确实必要参数", 404);
        }

        $request = array(
            'cid' => $data['mid'],
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['message']
        );

        // widget
        $editor = $this->singletonWidget(
            'Widget_Contents_Attachment_Edit', NULL, $request
        );

        // update
        $editor->updateAttachment();

        if ($editor->have()) {
            return Nabo_Format::mediaOf(
                end($editor->stack)
            );
        }
        return new Exception("更新失败");
    }

    /**
     * 内容替换 - 插件
     *
     * @access public
     * @param array $struct
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_plugin_replace($struct)
    {
        $this->checkAccess('administrator');

        if (empty($struct['former']) || empty($struct['last']) || empty($struct['object'])) {
            return new Exception("确实必要参数", 404);
        } else {
            $former = $struct['former'];
            $last = $struct['last'];
            $object = $struct['object'];
            $array = [
                'post|text',
                'post|title',
                'page|text',
                'page|title',
                'field|thumb',
                'field|mp4',
                'field|fm',
                'comment|text',
                'comment|url'
            ];
            if (in_array($object, $array)) {
                $prefix = $this->db->getPrefix();
                $obj = explode("|", $object);
                $type = $obj[0];
                $aim = $obj[1];
                switch ($type) {
                    case "post":
                    case "page":
                        $data_name = $prefix . 'contents';
                        $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}') WHERE type='{$type}'");
                        break;
                    case "field":
                        $data_name = $prefix . 'fields';
                        $this->db->query("UPDATE `{$data_name}` SET `str_value`=REPLACE(`str_value`,'{$former}','{$last}')  WHERE name='{$aim}'");
                        break;
                    case "comment":
                        $data_name = $prefix . 'comments';
                        $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}')");
                }
                return "替换成功";

            } else {
                return new Exception("不含此参数,无法替换", 202);
            }
        }
    }

    /**
     * 我的动态 - 插件
     *
     * @access public
     * @param array $struct
     * @return array|Exception|int
     * @throws Exception
     */
    public function metcip_kat_plugin_dynamics_select($struct)
    {
        $this->checkAccess('editor');

        if (!isset($this->options->plugins['activated']['Dynamics'])) {
            return new Exception('没有启用我的动态插件', 404);
        }

        Nabo_Format::pagingOf($struct, $page, $size);

        $list = $this->pluginHandle('Nabo_Dynamics')
            ->trigger($dynamicPluggable)
            ->select($this->liteuser->uid(), $struct['status'], $size, $page);

        if ($dynamicPluggable) {
            return Nabo_Format::dynamicsOf($list);
        }

        return new Exception('动态插件不存在');
    }

    /**
     * 我的动态 - 插件
     *
     * @access public
     * @param array $data
     * @return array|Exception|int
     * @throws Exception
     */
    public function metcip_kat_plugin_dynamics_insert($data)
    {
        $this->checkAccess('editor');

        if (!is_array($data)) {
            return new Exception("非法请求");
        }


        if (empty($data['text'])) {
            return new Exception('无动态内容', 404);
        }
        $date = $this->options->time;

        $dynamic = [
            'authorId' => $this->liteuser->uid(),
            'text' => $data['text'],
            'status' => $data['status'],
            'modified' => $date
        ];

        if ($insert = empty($did = $data['did'])) {
            $dynamic['created'] = $date;
        } else {
            $dynamic['did'] = $did;
        }

        $result = $this->pluginHandle('Nabo_Dynamics')
            ->trigger($dynamicPluggable)
            ->{$insert ? 'insert' : 'modify'}($this->liteuser->uid(), $dynamic);
        if ($dynamicPluggable) {
            return Nabo_Format::dynamicOf($result);
        }

        return new Exception('动态插件不存在');
    }

    /**
     * 我的动态 - 插件
     *
     * @access public
     * @param array $did
     * @return Exception|int
     * @throws Exception
     */
    public function metcip_kat_plugin_dynamics_delete($did)
    {
        $this->checkAccess('editor');

        if (!is_array($did)) {
            return new Exception("非法请求");
        }

        $deleteCount = $this->pluginHandle('Nabo_Dynamics')
            ->trigger($dynamicPluggable)
            ->delete($this->liteuser->uid(), $did);

        if ($dynamicPluggable) {
            return $deleteCount;
        }

        return new Exception('动态插件不存在');
    }

    /**
     * 友情链接 - 插件
     *
     * @access public
     * @param array $struct
     * @return array|Exception|int
     * @throws Exception
     */
    public function metcip_kat_plugin_links_select($struct)
    {
        $this->checkAccess('editor');

        if (!isset($this->options->plugins['activated']['Links'])) {
            return new Exception('没有启用友情链接插件', 404);
        }

        Nabo_Format::pagingOf($struct, $page, $size);

        $select = $this->db->select()
            ->from('table.links')
            ->order('order')
            ->page($page, $size);

        $list = [];
        foreach ($this->db->fetchAll($select) as $link) {
            settype($link['lid'], 'int');
            settype($link['order'], 'int');
            $list[] = $link;
        }
        return $list;
    }

    /**
     * 友情链接 - 插件
     *
     * @access public
     * @param array $data
     * @return array|Exception|int
     * @throws Exception
     */
    public function metcip_kat_plugin_links_insert($data)
    {
        $this->checkAccess('editor');

        if (!is_array($data)) {
            return new Exception("非法请求", 403);
        }

        if (!isset($data['name'])) {
            return new Exception("没有设定名字");
        }

        if (!isset($data['url'])) {
            return new Exception("没有设定链接地址");
        }

        $link = array(
            'name' => $data['name'],
            'url' => $data['url'],
            'image' => $data['image'],
            'description' => $data['description'],
            'user' => $data['url'],
            'order' => $data['order'],
            'sort' => $data['sort']
        );

        if (empty($data['lid'])) {
            $link['order'] = $this->db->fetchObject($this->db
                    ->select(array('MAX(order)' => 'maxOrder'))
                    ->from('table.links'))->maxOrder + 1;
            $link['lid'] = $this->db->query($this->db->insert('table.links')->rows($link));
        } else {
            $this->db->query($this->db->update('table.links')
                ->rows($link)
                ->where('lid = ?', $data['lid']));
        }

        settype($link['lid'], 'int');
        settype($link['order'], 'int');
        return $link;
    }

    /**
     * 友情链接 - 插件
     *
     * @access public
     * @param array $lid
     * @return array|Exception|int
     * @throws Exception
     */
    public function metcip_kat_plugin_links_delete($lid)
    {
        $this->checkAccess('editor');

        if (!is_array($lid)) {
            return new Exception("缺少参数");
        }

        $deleteCount = 0;
        foreach ($lid as $id) {
            if ($this->db->query($this->db->delete('table.links')->where('lid = ?', $id))) {
                $deleteCount++;
            }
        }

        return $deleteCount;
    }

    /**
     * 插件配置管理
     *
     * @access public
     * @param array $data
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_config_plugin($data)
    {
        $this->checkAccess('administrator');

        if (empty($this->option->allowPlugin)) {
            return new Exception("已关闭插件设置能力\n可以在南博插件里开启设置能力", 202);
        }

        if (!isset($data['pluginName'])) {
            return new Exception("缺少必要参数", 403);
        }

        if (!isset($this->options->plugins['activated'][$data['pluginName']])) {
            return new Exception("没有启用插件", 403);
        }

        $className = "{$data['pluginName']}_Plugin";
        if ($data['method'] == 'set') {
            if (empty($data['settings'])) {
                return new Exception("settings 不规范");
            }

            $settings = json_decode($data['settings'], true);

            ob_start();
            $form = new Typecho_Widget_Helper_Form();
            call_user_func(array($className, 'config'), $form);

            foreach ($settings as $key => $val) {
                if (!empty($form->getInput($key))) {
                    $_GET[$key] = $settings[$key];
                    $form->getInput($key)->value($val);
                }
            }

            /** 验证表单 */
            if ($form->validate()) {
                return new Exception("表中有数据不符合配置要求");
            }

            $settings = $form->getAllRequest();
            ob_end_clean();

            $edit = $this->singletonWidget(
                'Widget_Plugins_Edit'
            );
            if (!$edit->configHandle($data['pluginName'], $settings, false)) {
                Widget_Plugins_Edit::configPlugin($data['pluginName'], $settings);
            }

            return '设置成功';
        }

        ob_start();
        $config = $this->singletonWidget(
            'Widget_Plugins_Config', NULL, ['config' => $data['pluginName']]
        );
        $form = $config->config();
        $form->setAction(NULL);
        $form->setAttribute("id", "form");
        $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
        $form->render();

        return ob_get_clean();
    }

    /**
     * @param $data
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_config_profile($data)
    {
        $this->checkAccess('administrator');

        if (!isset($data['option'])) {
            return new Exception("缺少必要参数", 403);
        }

        if ($data['method'] == 'set') {

            if (empty($this->option->allowSetting)) {
                return new Exception("已关闭基本设置能力\n可以在南博插件里开启设置能力", 202);
            }

            if (empty($data['settings'])) {
                return new Exception("settings 不规范");
            }
            $settings = json_decode($data['settings'], true);

            ob_start();
            $config = $this->singletonWidget(
                'Widget_Users_Profile',
                NULL,
                $settings
            );
            if ($data['option'] == "profile") {
                $config->updateProfile();
            } else if ($data['option'] == "options") {
                $config->updateOptions();
            } else if ($data['option'] == "password") {
                $config->updatePassword();
//            } else if ($struct['option'] == "personal") {
//                $config->updatePersonal();
            }
            ob_end_clean();

            return '设置已经保存';
        }

        ob_start();
        $config = $this->singletonWidget(
            'Widget_Users_Profile'
        );

        if ($data['option'] == "profile") {
            $form = $config->profileForm();
        } else if ($data['option'] == "options") {
            $form = $config->optionsForm();
        } else if ($data['option'] == "password") {
            $form = $config->passwordForm();
//            } else if ($struct['option'] == "personal") {
//                $form = $config->personalFormList();
        } else {
            return new Exception("option 不规范");
        }

        $form->setAction(NULL);
        $form->setAttribute("id", "form");
        $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
        $form->render();

        return ob_get_clean();
    }

    /**
     * @param $data
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_config_option($data)
    {
        $this->checkAccess('administrator');

        if (!isset($data['option'])) {
            return new Exception("缺少必要参数", 403);
        }

        if ($data['option'] == "general") {
            $alias = "Widget_Options_General";
        } else if ($data['option'] == "discussion") {
            $alias = "Widget_Options_Discussion";
        } else if ($data['option'] == "reading") {
            $alias = "Widget_Options_Reading";
        } else if ($data['option'] == "permalink") {
            $alias = "Widget_Options_Permalink";
        } else {
            return new Exception("option 不规范");
        }

        if ($data['method'] == 'set') {
            if (empty($this->option->allowSetting)) {
                return new Exception("已关闭基本设置能力\n可以在南博插件里开启设置能力", 202);
            }

            if (empty($data['settings'])) {
                return new Exception("settings 不规范");
            }
            $settings = json_decode($data['settings'], true);

            ob_start();
            $config = $this->singletonWidget(
                $alias,
                null,
                $settings
            );
            if ($data['option'] == "general") {
                $config->updateGeneralSettings();
            } else if ($data['option'] == "discussion") {
                $config->updateDiscussionSettings();
            } else if ($data['option'] == "reading") {
                $config->updateReadingSettings();
            } else if ($data['option'] == "permalink") {
                $config->updatePermalinkSettings();
            }
            ob_end_clean();

            return '设置已经保存';
        }

        ob_start();
        $config = $this->singletonWidget($alias);
        $form = $config->form();
        $form->setAction(NULL);
        $form->setAttribute("id", "form");
        $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
        $form->render();

        return ob_get_clean();
    }

    /**
     * 主题配置管理
     *
     * @access public
     * @param array $data
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_config_theme($data)
    {
        $this->checkAccess('administrator');

        if (empty($this->option->allowTheme)) {
            return new Exception("已关闭主题设置能力\n可以在南博插件里开启设置能力", 202);
        }

        if (!Widget_Themes_Config::isExists()) {
            return new Exception('没有主题可配置', 404);
        }

        if ($data['method'] == 'set') {
            if (empty($data['settings'])) {
                return new Exception("settings 不规范");
            }

            $settings = json_decode($data['settings'], true);
            $theme = $this->options->theme;

            ob_start();
            $form = new Typecho_Widget_Helper_Form();
            themeConfig($form);
            $inputs = $form->getInputs();

            if (!empty($inputs)) {
                foreach ($inputs as $key => $val) {
                    $_GET[$key] = $settings[$key];
                    $form->getInput($key)->value($settings[$key]);
                }
            }

            /** 验证表单 */
            if ($form->validate()) {
                return new Exception("表中有数据不符合配置要求");
            }

            $settings = $form->getAllRequest();
            ob_end_clean();

            $db = Typecho_Db::get();
            $themeEdit = $this->singletonWidget(
                'Widget_Themes_Edit'
            );

            if (!$themeEdit->configHandle($settings, false)) {
                if ($this->options->__get('theme:' . $theme)) {
                    $update = $db->update('table.options')
                        ->rows(array('value' => serialize($settings)))
                        ->where('name = ?', 'theme:' . $theme);
                    $db->query($update);
                } else {
                    $insert = $db->insert('table.options')
                        ->rows(array(
                            'name' => 'theme:' . $theme,
                            'value' => serialize($settings),
                            'user' => 0
                        ));
                    $db->query($insert);
                }
            }

            return '外观设置已经保存';
        }

        ob_start();
        $config = $this->singletonWidget(
            'Widget_Themes_Config'
        );
        $form = $config->config();
        $form->setAction(NULL);
        $form->setAttribute("id", "form");
        $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
        $form->render();

        return ob_get_clean();
    }

    /**
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_plugins_list($data)
    {
        $this->checkAccess('administrator');

        $target = $data['option'] ?: 'typecho';
        $list = [];
        $activatedPlugins = $this->singletonWidget('Widget_Plugins_List@activated', 'activated=1');

        if ($activatedPlugins->have() || !empty($activatedPlugins->activatedPlugins)) {
            while ($activatedPlugins->next()) {
                $list[$activatedPlugins->name] = array(
                    "activated" => true,
                    "name" => $activatedPlugins->name,
                    "title" => $activatedPlugins->title,
                    "dependence" => $activatedPlugins->dependence,
                    "description" => strip_tags($activatedPlugins->description),
                    "version" => $activatedPlugins->version,
                    "homepage" => $activatedPlugins->homepage,
                    "author" => $activatedPlugins->author,
                    "config" => $activatedPlugins->config
                );
            }
        }

        $deactivatedPlugins = $this->singletonWidget('Widget_Plugins_List@unactivated', 'activated=0');

        if ($deactivatedPlugins->have() || !$activatedPlugins->have()) {
            while ($deactivatedPlugins->next()) {
                $list[$deactivatedPlugins->name] = array(
                    "activated" => false,
                    "name" => $deactivatedPlugins->name,
                    "title" => $deactivatedPlugins->title,
                    "dependence" => true,
                    "description" => strip_tags($deactivatedPlugins->description),
                    "version" => $deactivatedPlugins->version,
                    "homepage" => $deactivatedPlugins->homepage,
                    "author" => $deactivatedPlugins->author,
                    "config" => false
                );
            }
        }

        if ($target == 'testore') {
            $activatedList = $this->options->plugins['activated'];
            if (isset($activatedList['TeStore'])) {
                $testore = $this->singletonWidget(
                    "TeStore_Action"
                );
                $storeList = array();
                $plugins = $testore->getPluginData();

                foreach ($plugins as $plugin) {
                    $thisPlugin = $list[$plugin['pluginName']];
                    $installed = array_key_exists($plugin['pluginName'], $list);
                    $activated = $installed ? $thisPlugin["activated"] : false;
                    $storeList[] = array(
                        "activated" => $activated,
                        "name" => $plugin['pluginName'],
                        "title" => $plugin['pluginName'],
                        "dependence" => $activated ? $thisPlugin["dependence"] : null,
                        "description" => strip_tags($plugin['desc']),
                        "version" => $plugin['version'],
                        "homepage" => $plugin['pluginUrl'],
                        "author" => strip_tags($plugin['authorHtml']),
                        "config" => $activated ? $thisPlugin["config"] : false,

                        "installed" => $installed,
                        "mark" => $plugin['mark'],
                        "zipFile" => $plugin['zipFile'],
                    );
                }

                return $storeList;
            } else {
                return new Exception("你没有安装 TeStore 插件", 301);
            }
        } else {
            $callList = array();
            foreach ($list as $key => $info) {
                $callList[] = $info;
            }
            return $callList;
        }
    }

    /**
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_plugins_merge($data)
    {
        $this->checkAccess('administrator');

        if (empty($this->option->allowPlugin)) {
            return new Exception("已关闭插件设置能力\n可以在南博插件里开启设置能力", 202);
        }

        $target = $data['option'] ?: 'typecho';
        if ($target == 'testore') {
            $activatedList = $this->options->plugins['activated'];
            if (isset($activatedList['TeStore'])) {
                $authors = preg_split('/([,&])/', $data['authorName']);
                foreach ($authors as $key => $val) {
                    $authors[$key] = trim($val);
                }
                $testore = $this->singletonWidget(
                    'TeStore_Action',
                    null,
                    array(
                        'plugin' => $data['pluginName'],
                        'author' => implode('_', $authors),
                        'zip' => $data['zipFile'],
                    )
                );

                $isActivated = $activatedList[$data['pluginName']];

                if ($data['method'] == 'activate') {
                    if ($isActivated) {
                        return new Exception("该插件已被安装过", 401);
                    } else {
                        $testore->install();
                    }
                } else if ($data['method'] == 'deactivate') {
                    $testore->uninstall();
                }

                return Json::decode(
                    Typecho_Cookie::get("__typecho_notice"), true
                )[0];
            } else {
                return new Exception("你没有安装 TeStore 插件", 301);
            }
        } else {
            $plugins = $this->singletonWidget(
                'Widget_Plugins_Edit'
            );

            if ($data['method'] == 'activate') {
                $plugins->activate($data['pluginName']);

            } else if ($data['method'] == "deactivate") {
                $plugins->deactivate($data['pluginName']);

            }

            return Json::decode(
                Typecho_Cookie::get("__typecho_notice"), true
            )[0];
        }
    }

    /**
     * @param $data
     * @return array|Exception
     * @throws Exception
     */
    public function metcip_kat_themes_list($data)
    {
        $this->checkAccess('administrator');

        $list = [];
        $themes = $this->singletonWidget('Widget_Themes_List');

        while ($themes->next()) {
            $list[] = array(
                "activated" => $themes->activated,
                "name" => $themes->name,
                "title" => $themes->title,
                "description" => strip_tags($themes->description),
                "version" => $themes->version,
                "homepage" => $themes->homepage,
                "author" => $themes->author,
                "config" => false
            );
        }

        return $list;
    }

    /**
     * @param $data
     * @return string|Exception
     * @throws Exception
     */
    public function metcip_kat_themes_merge($data)
    {
        $this->checkAccess('administrator');

        if (empty($this->option->allowTheme)) {
            return new Exception("已关闭主题设置能力\n可以在南博插件里开启设置能力", 202);
        }

        $themes = $this->singletonWidget(
            'Widget_Themes_Edit'
        );

        if ($data['method'] == 'changeTheme') {
            $themes->changeTheme($data['themeName']);
            return "外观已经改变";
        }

        return new Exception("未知错误");
    }
}