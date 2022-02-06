<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 南博 KAT-RPC 接口
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
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
        // data
        $request = file_get_contents(
            'php://input'
        );

        // check data
        if (empty($request)) {
            die('Kat server accepts POST requests only');
        }

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
        include_once 'Kat/Plus.php';
        include_once 'Kat/Server.php';

        // server
        $this->server = new Kat_Server();

        // register
        $this->server->register($this);

        // launch
        $this->server->receive(
            $this, $request
        );
    }

    /**
     * @param $alias
     * @param null $params
     * @param null $request
     * @param false $enableResponse
     * @return mixed
     */
    private function singletonWidget($alias, $params = NULL, $request = NULL, $enableResponse = false)
    {
        $widget = Typecho_Widget::widget(
            $alias, $params, $request, $enableResponse
        );
        if ($enableResponse) {
            $widget->response = new Nabo_Helper();
        }
        return $widget;
    }

    /**
     * @return void
     */
    public function hooker_start()
    {
        // stream
    }

    /**
     * @param $kat
     * @param $header
     */
    public function hooker_end($kat, $header)
    {
        // kat
        foreach ($header as $key => $val) {
            header("$key: $val");
        }
        header('Date: ' . date('r'));
        header('Connection: close');
        header('Content-Length: ' . strlen($kat));
        exit($kat);
    }

    /**
     * @param $kat
     * @throws Crash
     * @throws ReflectionException
     */
    public function hooker_accept(&$kat)
    {
        // cert
        $cert = $kat['cert'];

        // digest
        $digest = md5(
            $kat['request']
        );

        // check
        if ($cert['digest'] != $digest) {
            throw new Crash(
                '非法请求', 102
            );
        }

        // cert
        $auth = $kat['auth'];

        // identity
        $this->liteuser->identity($auth);

        // check
        switch ($kat['method']) {
            case 'kat_user_login':
            case 'kat_user_challenge':
                break;
            default:
            {
                // challenge
                $this->liteuser->register();

                // modify
                $this->user->execute();
            }
        }
    }

    /**
     * @param $kat
     * @param $callback
     */
    public function hooker_challenge($kat, &$callback)
    {
        $callback['cert'] = [
            'digest' => md5($callback['response'])
        ];
    }

    /**
     * 验证权限
     *
     * @param string $level
     * @throws Crash
     */
    public function check_access($level = 'contributor')
    {
        if (!$this->user->pass($level, true)) {
            throw new Crash('权限不足', 101);
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
     * @param $data
     * @return array|Crash
     */
    public function metcip_kat_user_login($data)
    {
        return $this->liteuser->login($data);
    }

    /**
     * @param $credential
     * @return array|Crash
     */
    public function metcip_kat_user_challenge($credential)
    {
        return $this->liteuser->challenge($credential);
    }

    /**
     * @param $data
     * @return array|string[]
     * @throws Crash
     */
    public function metcip_kat_user_pull($data)
    {
        $this->check_access();

        // touch
        $touch = (int)$data['touch'];

        // callback
        $callback = array(
            'region' => 'sync'
        );

        // user
        $user = $this->liteuser->user;

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

        // merge
        if ($touch) {
            $callback = array_merge(
                $callback, $this->liteuser->response($user)
            );
        }

        return $callback;
    }

    /**
     * @param $data
     * @return array
     * @throws Crash
     */
    public function metcip_kat_stat_pull($data)
    {
        $this->check_access();

        return [
            'creative' => Nabo_Format::create_words(
                $this->liteuser->uid
            )
        ];
    }

    /**
     * @param $data
     * @return KatAry|Crash
     * @throws Crash
     */
    public function metcip_kat_note_drag($data)
    {
        // check type
        switch ($type = $data['type']) {
            case 'post':
                $this->check_access();
                break;
            case 'page':
                $this->check_access('editor');
                break;
            default:
                return new Crash('异常请求');
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
            $select->where('table.contents.authorId = ?', $this->liteuser->uid);
        }

        // status
        switch ($status = $data['status'] ?: 'allow') {
            case 'allow':
                $select->where('table.contents.type = ?', $type);
                break;
            case 'draft':
                $select->where('table.contents.type LIKE ?', '%_draft');
                break;
            default:
                $select->where('table.contents.type = ?', $type);
                $select->where('table.contents.status = ?', Nabo_Format::note_status($status));
        }

        // search
        if (isset($data['search'])) {
            $searchQuery = Nabo_Format::search($data['search']);
            $select->where('table.contents.title LIKE ? OR table.contents.text LIKE ?', $searchQuery, $searchQuery);
        }

        // order
        if ($type == 'page') {
            $select->order('table.contents.order');
        } else {
            $select->order('table.contents.' . ($status == 'draft' ? 'modified' : 'created'), Typecho_Db::SORT_DESC);
        }

        // paging
        Nabo_Format::paging(
            $data, $page, $size
        );

        // page
        $select->page(
            $page, $size
        );

        return Nabo_Format::notes(
            $this->db->fetchAll($select)
        );
    }

    /**
     * @param $data
     * @return KatAny|Crash
     * @throws Crash
     */
    public function metcip_kat_note_pull($data)
    {
        // check type
        switch ($type = $data['type']) {
            case 'post':
                $this->check_access();
                break;
            case 'page':
                $this->check_access('editor');
                break;
            default:
                return new Crash('异常请求');
        }

        $note = $this->db->fetchRow($this->db->select()
            ->from('table.contents')
            ->where('cid = ?', intval($data['nid']))
            ->where('authorId = ?', $this->liteuser->uid));

        if (empty($note)) {
            return new Crash(
                '笔记不存在', 403
            );
        }

        return Nabo_Format::note($note);
    }

    /**
     * @param $data
     * @return array|Crash
     * @throws Crash
     * @throws ReflectionException
     */
    public function metcip_kat_note_push($data)
    {
        // check type
        switch ($type = $data['type']) {
            case 'post':
                $this->check_access();
                break;
            case 'page':
                $this->check_access('editor');
                break;
            default:
                return new Crash('异常请求');
        }

        // do
        $do = ($draft = $data['status'] == 'draft') ? 'save' : 'publish';

        // widget
        $editor = $this->singletonWidget('Widget_Contents_Post_Edit');

        // push
        if (!empty($data['nid'])) {
            if ($temporary = $this->db->fetchRow($editor->select()
                ->where('cid = ? and type LIKE ?', $data['nid'], $type . '%'))) {
                if ($temporary['authorId'] != $this->liteuser->uid ||
                    !$this->user->pass('editor', true)) {
                    return new Crash('没有编辑权限');
                }
                if ($temporary['parent']) {
                    $temporary['draft']['cid'] = $temporary[$draft ? 'cid' : 'parent'];
                }
                $editor->push($temporary);
            } else {
                return new Crash('笔记不存在, 无法编辑');
            }
        }

        // draft
        if ($draft) {
            $type .= '_draft';
        }

        // note
        $note = array(
            // base
            'type' => $type,
            'text' => $data['content'],

            // status
            'visibility' => Nabo_Format::note_status($data['status'])
        );

        // markdown
        if ($this->user->markdown) {
            $note['text'] = '<!--markdown-->' . $note['text'];
        }

        // slug
        if (isset($data['slug'])) {
            $note['slug'] = $data['slug'];
        }

        // code
        if (!empty($data['code'])) {
            $note['password'] = $data['code'];
            $note['visibility'] = 'password';
        }

        // envoy
        if (!empty($data['envoy'])) {
            $note['template'] = $data['envoy'];
        }

        // envoy
        if (isset($data['allowDisc'])) {
            $note['allowComment'] = $data['allowDisc'];
        }

        // filed
        foreach (['title', 'order', 'allowPing', 'allowFeed'] as $filed) {
            if (isset($data[$filed])) {
                $note[$filed] = $data[$filed];
            }
        }
        unset($filed);

        // fields
        if (is_array($data['extras'])) {
            foreach ($data['extras'] as $field) {
                $note['fields'][$field['k']] = [
                    $field['t'], $field['v']
                ];
            }
        }

        // meta
        $note['category'] = [];
        if (!empty($data['meta'])) {
            if (is_numeric($data['meta'])) {
                $note['category'] [] = $data['meta'];
            } else if (is_array($data['meta'])) {
                $note['category'] = $data['meta'];
            }
        }

        // tags
        if (!empty($data['tags'])) {
            $note['tags'] = $data['tags'];
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
            'nid' => intval($editor->cid)
        );
    }

    /**
     * @param $data
     * @return bool
     * @throws Crash
     */
    public function metcip_kat_note_roll($data)
    {
        $nid = intval($data['nid']);
        $alias = 'Widget_Contents_Post_Edit';

        if ($nid > 0) switch ($data['type']) {
            case 'post':
                $this->check_access();
                break;
            case 'page':
                $this->check_access('editor');
                $alias = 'Widget_Contents_Page_Edit';
                break;
            default:
                return false;
        }

        $this->singletonWidget(
            $alias, NULL, ['cid' => $nid]
        )->deletePost();

        return Nabo_Format::success();
    }

    /**
     * @param $data
     * @return false|string
     * @throws Crash
     */
    public function metcip_kat_note_extra($data)
    {
        $this->check_access();

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
     * @param $data
     * @return KatAry
     * @throws Crash
     */
    public function metcip_kat_discuss_drag($data)
    {
        $this->check_access();

        // select
        $select = $this->db->select('table.comments.*', 'table.contents.title')->from('table.comments')
            ->join('table.contents', 'table.comments.cid = table.contents.cid', Typecho_Db::LEFT_JOIN);

        // total
        if ($data['total'] != 'on' || !$this->user->pass('editor', true)) {
            $select->where('table.comments.ownerId = ?', $this->liteuser->uid);
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
            $select->where('table.comments.status = ?', Nabo_Format::discuss_status($data['status']));
        }

        // search
        if (isset($struct['search'])) {
            $searchQuery = Nabo_Format::search($struct['search']);
            $select->where('table.comments.author LIKE ? OR table.comments.text LIKE ? OR table.comments.url LIKE ?',
                $searchQuery, $searchQuery, $searchQuery
            );
        }

        // paging
        Nabo_Format::paging($data, $page, $size);

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

        return Nabo_Format::discuses($comments);
    }

    /**
     * @param $did
     * @return Crash|KatAny
     * @throws Crash
     */
    public function metcip_kat_discuss_pull($did)
    {
        $this->check_access();

        // check
        if (empty($did)) {
            return new Crash('评论不存在', 404);
        }

        // select
        $select = $this->db->select('table.comments.*', 'table.contents.title')->from('table.comments')
            ->join('table.contents', 'table.comments.cid = table.contents.cid', Typecho_Db::LEFT_JOIN);

        // comment
        if (empty($comment = $this->db->fetchRow($select->where('coid = ?', $did)->limit(1)))) {
            return new Crash('评论不存在', 404);
        }

        // total
        if ($comment['ownerId'] != $this->liteuser->uid || !$this->user->pass('editor', true)) {
            return new Crash('没有获取评论的权限', 403);
        }

        return Nabo_Format::discuss($comment);
    }

    /**
     * @param $data
     * @return Crash|KatAny
     * @throws Crash
     * @throws ReflectionException
     */
    public function metcip_kat_discuss_push($data)
    {
        $this->check_access();

        // nid
        $nid = (int)$data['nid'];

        // rely
        $rely = (int)$data['rely'];

        // check
        if ($data['did'] > 0) {
            $request = array(
                'coid' => $data['did']
            );

            if (isset($data['mail'])) {
                $request['mail'] = $data['mail'];
            }

            if (isset($data['site'])) {
                $request['url'] = $data['site'];
            }

            if (isset($data['author'])) {
                $request['author'] = $data['author'];
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
                return Nabo_Format::discuss(
                    $extract['comment']
                );
            }

            return new Crash(
                $extract['message']
            );
        }

        if (!isset($data['message'])) {
            return new Crash(
                '评论内容为空', 404
            );
        }

        if (empty($row = $this->db->fetchRow(
            $this->select()->where('cid = ?', $nid)->limit(1)))) {
            return new Crash(_t('笔记不存在'), 404);
        }

        // push
        $this->push($row);

        $request = array(
            'type' => 'comment',
            'text' => $data['message'],
            'parent' => $rely,
        );

        // close anti
        Helper::options()->commentsAntiSpam = false;

        // widget
        $editor = $this->singletonWidget(
            'Widget_Feedback', NULL, $request
        );

        // reflection
        $ref = new ReflectionClass($editor);
        $property_content = $ref->getProperty(
            property_exists($editor, 'content')
                ? 'content' : '_content'
        );

        $property_content->setAccessible(true);
        $property_content->setValue($editor, $this);

        $comment = $ref->getMethod('comment');
        $comment->setAccessible(true);

        ob_start();
        try {
            // comment
            $comment->invoke($editor);
        } catch (Exception $e) {
            ob_end_clean();
            return new Crash($e->getMessage());
        }
        ob_end_clean();

        return Nabo_Format::discuss(
            Nabo_Format::lastOf($editor)
        );
    }

    /**
     * @param $data
     * @return bool|Crash
     * @throws Crash
     * @throws ReflectionException
     */
    public function metcip_kat_discuss_mark($data)
    {
        $this->check_access('editor');

        if (empty($did = $data['did'])) {
            return new Crash('评论不存在');
        }

        if (empty($status = $data['status'])) {
            return new Crash(
                '不必更新的情况'
            );
        }

        $editor = $this->singletonWidget('Widget_Comments_Edit');
        $mark = (new ReflectionClass($editor))->getMethod('mark');
        $mark->setAccessible('mark');

        if ($mark->invoke($editor, $did,
            Nabo_Format::discuss_status($status))) {
            return true;
        }

        return false;
    }

    /**
     * @param $data
     * @return bool|Crash
     * @throws Crash
     */
    public function metcip_kat_discuss_roll($data)
    {
        $this->check_access('editor');

        // did
        if (empty($did = $data['did'])) {
            return new Crash('评论不存在', 404);
        }

        // widget
        $this->singletonWidget(
            'Widget_Comments_Edit',
            NULL, ['coid' => [$did]]
        )->deleteComment();

        return Nabo_Format::success();
    }

    /**
     * @param $data
     * @return KatAry
     * @throws Crash
     */
    public function metcip_kat_meta_drag($data)
    {
        $this->check_access('editor');

        // fetch
        $metas = $this->db->fetchAll($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->order('table.metas.order'));

        return Nabo_Format::metas($metas);
    }

    /**
     * @param $data
     * @return Crash|KatAny
     * @throws Crash
     */
    public function metcip_kat_meta_push($data)
    {
        $this->check_access('editor');

        if (empty($data['mid'])) {
            $meta = array(
                'name' => $data['name'],
                'slug' => $data['slug'],
                'parent' => intval($data['rely'])
            );

            // widget
            $editor = $this->singletonWidget(
                'Widget_Metas_Category_Edit', NULL, $meta
            )->insertCategory();

            return Nabo_Format::meta(
                Nabo_Format::lastOf($editor)
            );
        } else {
            // fetch
            if (empty($this->db->fetchRow(
                $this->db->select('mid')->from('table.metas')
                    ->where('type = ? AND mid = ?', 'category', $data['mid'])))) {
                return new Crash(
                    '没有查找到分类', 404
                );
            }

            // meta
            $meta = array();

            if (isset($data['name'])) {
                $meta['name'] = $data['name'];
            }

            if (isset($data['slug'])) {
                $meta['slug'] = $data['slug'];
            }

            if (isset($data['rely'])) {
                $meta['parent'] = $data['rely'];
            }

            // widget
            $editor = $this->singletonWidget(
                'Widget_Metas_Category_Edit', NULL, $meta
            )->updateCategory();

            return Nabo_Format::meta(
                Nabo_Format::lastOf($editor)
            );
        }
    }

    /**
     * @param $mid
     * @return bool|Crash
     * @throws Crash
     */
    public function metcip_kat_meta_roll($mid)
    {
        $this->check_access('editor');

        if (empty($mid)) {
            return new Crash(
                '分类不存在'
            );
        }

        $this->singletonWidget(
            'Widget_Metas_Category_Edit',
            NULL, ['mid' => [$mid]]
        )->deleteCategory();

        return Nabo_Format::success();
    }

    /**
     * @param $data
     * @return bool|Crash
     * @throws Crash
     */
    public function metcip_kat_meta_sort($data)
    {
        $this->check_access('editor');

        if (!is_array($metas = $data['metas'])) {
            return new Crash(
                '分类集群错误'
            );
        }

        if (!in_array($type = $data['type'], ['category', 'tag'])) {
            return new Crash(
                '无法识别'
            );
        }

        $this->singletonWidget(
            'Widget_Abstract_Metas'
        )->sort(['mid' => $metas], $type);

        return true;
    }

    /**
     * @param $data
     * @return KatAry
     * @throws Crash
     */
    public function metcip_kat_media_drag($data)
    {
        $this->check_access();

        // select
        $select = $this->select()->where('table.contents.type = ?', 'attachment');

        if (!$this->user->pass('editor', true)) {
            $select->where('table.contents.authorId = ?', $this->liteuser->uid);
        }

        // search
        if (isset($data['search'])) {
            $searchQuery = Nabo_Format::search($data['search']);
            $select->where('table.contents.title LIKE ? OR table.contents.text LIKE ?',
                $searchQuery, $searchQuery
            );
        }

        Nabo_Format::paging($data, $page, $size);

        // page
        $select->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->page($page, $size);

        return Nabo_Format::medias(
            $this->db->fetchAll($select)
        );
    }

    /**
     * @param $data
     * @return bool|Crash
     * @throws Crash
     */
    public function metcip_kat_media_roll($data)
    {
        $this->check_access();

        if (empty($mid = $data['mid'])) {
            return new Crash(
                '缺少必要参数', 404
            );
        }

        // widget
        $this->singletonWidget(
            'Widget_Contents_Attachment_Edit',
            NULL, ['cid' => [$mid]]
        )->deleteAttachment();

        return Nabo_Format::success();
    }

    /**
     * @param $data
     * @return bool
     * @throws Crash
     */
    public function metcip_kat_media_clear($data)
    {
        $this->check_access('editor');

        $this->singletonWidget('Widget_Contents_Attachment_Edit')->clearAttachment();

        return Nabo_Format::success();
    }

    /**
     * @param $data
     * @return Crash|KatAny
     * @throws Crash
     */
    public function metcip_kat_media_push($data)
    {
        $this->check_access();

        if (empty($data['mid']) || empty($data['name'])) {
            return new Crash('缺少必要参数', 404);
        }

        $request = array(
            'cid' => $data['mid'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['intro'],
        );

        // widget
        $editor = $this->singletonWidget(
            'Widget_Contents_Attachment_Edit', NULL, $request
        );

        // update
        $editor->updateAttachment();

        if ($editor->have()) {
            return Nabo_Format::media(
                Nabo_Format::lastOf($editor)
            );
        }
        return new Crash('更新失败');
    }

    /**
     * @param $struct
     * @return Crash|string
     * @throws Crash
     */
    public function metcip_kat_plugin_replace($struct)
    {
        $this->check_access('administrator');

        if (empty($struct['former']) || empty($struct['last']) || empty($struct['object'])) {
            return new Crash('缺少必要参数', 404);
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
                $obj = explode('|', $object);
                $type = $obj[0];
                $aim = $obj[1];
                switch ($type) {
                    case 'post':
                    case 'page':
                        $data_name = $prefix . 'contents';
                        $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}') WHERE type='{$type}'");
                        break;
                    case 'field':
                        $data_name = $prefix . 'fields';
                        $this->db->query("UPDATE `{$data_name}` SET `str_value`=REPLACE(`str_value`,'{$former}','{$last}')  WHERE name='{$aim}'");
                        break;
                    case 'comment':
                        $data_name = $prefix . 'comments';
                        $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}')");
                }
                return '替换成功';

            } else {
                return new Crash('不含此参数,无法替换', 202);
            }
        }
    }

    /**
     * @param $struct
     * @return Crash|KatAry
     * @throws Crash
     */
    public function metcip_kat_dynamic_drag($struct)
    {
        $this->check_access('editor');

        if (!isset($this->options->plugins['activated']['Dynamics'])) {
            return new Crash('没有启用我的动态插件', 404);
        }

        Nabo_Format::paging(
            $struct, $page, $size
        );

        $list = $this->pluginHandle('Nabo_Dynamics')
            ->trigger($pluggable)
            ->select(
                $this->liteuser->uid,
                Nabo_Format::dynamic_status($struct['status']), $size, $page
            );

        if ($pluggable) {
            return Nabo_Format::dynamics($list);
        }

        return new Crash('动态插件不存在');
    }

    /**
     * @param $data
     * @return Crash|KatAny
     * @throws Crash
     */
    public function metcip_kat_dynamic_push($data)
    {
        $this->check_access('editor');

        if (!is_array($data)) {
            return new Crash('非法请求');
        }

        if (empty($data['content'])) {
            return new Crash('无动态内容', 404);
        }

        $dynamic = array(
            'authorId' => $this->liteuser->uid,
            'text' => $data['content'],
            'status' => Nabo_Format::dynamic_status($data['status']),
            'modified' => $date = $this->options->time
        );

        if ($insert = empty($did = $data['did'])) {
            $dynamic['created'] = $date;
        } else {
            $dynamic['did'] = $did;
        }

        $result = $this->pluginHandle('Nabo_Dynamics')
            ->trigger($pluggable)
            ->{$insert ? 'insert' : 'modify'}(
                $this->liteuser->uid, $dynamic
            );
        if ($pluggable) {
            return Nabo_Format::dynamic($result);
        }

        return new Crash('动态插件不存在');
    }

    /**
     * @param $did
     * @return Crash
     * @throws Crash
     */
    public function metcip_kat_dynamic_roll($did)
    {
        $this->check_access('editor');

        if (!is_array($did)) {
            return new Crash('非法请求');
        }

        $deleteCount = $this->pluginHandle('Nabo_Dynamics')
            ->trigger($pluggable)
            ->delete(
                $this->liteuser->uid, $did
            );

        if ($pluggable) {
            return $deleteCount;
        }

        return new Crash('动态插件不存在');
    }

    /**
     * @param $data
     * @return Crash|KatAny|KatAry
     * @throws Crash
     */
    public function metcip_kat_friend_drag($data)
    {
        $this->check_access('editor');

        if (!isset($this->options->plugins['activated']['Links'])) {
            return new Crash('没有启用友情链接插件', 404);
        }

        Nabo_Format::paging(
            $data, $page, $size
        );

        $list = $this->pluginHandle('Nabo_Friend')
            ->trigger($pluggable)
            ->select(
                $this->liteuser->uid,
                strval($data['team']), $size, $page
            );

        if ($pluggable) {
            return Nabo_Format::friend($list);
        }

        $select = $this->db->select()
            ->from('table.links');

        if (!empty($data['team'])) {
            $select->where('sort = ?', $data['team']);
        }

        $select->order('order')
            ->page($page, $size);

        return Nabo_Format::friends(
            $this->db->fetchAll($select)
        );
    }

    /**
     * @param $data
     * @return Crash|KatAny
     * @throws Crash
     */
    public function metcip_kat_friend_push($data)
    {
        $this->check_access('editor');

        if (!is_array($data)) {
            return new Crash('非法请求', 403);
        }

        if (empty($data['name'])) {
            return new Crash('没有设定名字');
        }

        if (empty($data['link'])) {
            return new Crash('没有设定链接地址');
        }

        $link = array(
            'name' => $data['name'],
            'url' => $data['link'],
            'image' => $data['image'],
            'sort' => $data['team'],
            'user' => $data['extra']
        );

        if ($data['order'] > 0) {
            $link['order'] = $data['order'];
        }

        // do
        $insert = $data['fid'] < 1;

        $result = $this->pluginHandle('Nabo_Friend')
            ->trigger($pluggable)
            ->{$insert ? 'insert' : 'modify'}(
                $this->liteuser->uid, $link
            );
        if ($pluggable) {
            return Nabo_Format::friend($result);
        }

        if ($insert) {
            $link['order'] = $this->db->fetchObject($this->db
                    ->select(['MAX(order)' => 'maxOrder'])
                    ->from('table.links'))->maxOrder + 1;
            $link['lid'] = $this->db->query(
                $this->db->insert('table.links')->rows($link));
        } else {
            $this->db->query($this->db->update('table.links')
                ->where('lid = ?', $data['fid'])
                ->rows($link));
        }

        return Nabo_Format::friend($link);
    }

    /**
     * @param $lid
     * @return Crash|int
     * @throws Crash
     */
    public function metcip_kat_friend_roll($lid)
    {
        $this->check_access('editor');

        if (!is_array($lid)) {
            return new Crash('缺少参数');
        }

        $deleteCount = $this->pluginHandle('Nabo_Friend')
            ->trigger($pluggable)
            ->delete(
                $this->liteuser->uid, $lid
            );

        if ($pluggable) {
            return $deleteCount;
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
     * @param $data
     * @return Crash|false|string
     * @throws Crash
     */
    public function metcip_kat_setting_theme($data)
    {
        $this->check_access('administrator');

        if (empty($this->option->allowTheme)) {
            return new Crash("已关闭主题设置能力\n可以在南博插件里开启设置能力", 202);
        }

        if (!Widget_Themes_Config::isExists()) {
            return new Crash('没有主题可配置', 404);
        }

        if ($data['method'] == 'set') {
            if (empty($data['settings'])) {
                return new Crash('settings 不规范');
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
                return new Crash('表中有数据不符合配置要求');
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
        $form->setAttribute('id', 'form');
        $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
        $form->render();

        return ob_get_clean();
    }

    /**
     * @param $data
     * @return Crash|false|string
     * @throws Crash
     */
    public function metcip_kat_setting_plugin($data)
    {
        $this->check_access('administrator');

        if (empty($this->option->allowPlugin)) {
            return new Crash("已关闭插件设置能力\n可以在南博插件里开启设置能力", 202);
        }

        if (!isset($data['pluginName'])) {
            return new Crash('缺少必要参数', 403);
        }

        if (!isset($this->options->plugins['activated'][$data['pluginName']])) {
            return new Crash('没有启用插件', 403);
        }

        $className = "{$data['pluginName']}_Plugin";
        if ($data['method'] == 'set') {
            if (empty($data['settings'])) {
                return new Crash('settings 不规范');
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
                return new Crash('表中有数据不符合配置要求');
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
        $form->setAttribute('id', 'form');
        $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
        $form->render();

        return ob_get_clean();
    }

    /**
     * @param $data
     * @return string|Crash
     * @throws Crash
     */
    public function metcip_kat_setting_profile($data)
    {
        $this->check_access('administrator');

        if (!isset($data['option'])) {
            return new Crash('缺少必要参数', 403);
        }

        if ($data['method'] == 'set') {

            if (empty($this->option->allowSetting)) {
                return new Crash("已关闭基本设置能力\n可以在南博插件里开启设置能力", 202);
            }

            if (empty($data['settings'])) {
                return new Crash('settings 不规范');
            }
            $settings = json_decode($data['settings'], true);

            ob_start();
            $config = $this->singletonWidget(
                'Widget_Users_Profile',
                NULL,
                $settings
            );
            if ($data['option'] == 'profile') {
                $config->updateProfile();
            } else if ($data['option'] == 'options') {
                $config->updateOptions();
            } else if ($data['option'] == 'password') {
                $config->updatePassword();
//            } else if ($struct['option'] == 'personal') {
//                $config->updatePersonal();
            }
            ob_end_clean();

            return '设置已经保存';
        }

        ob_start();
        $config = $this->singletonWidget(
            'Widget_Users_Profile'
        );

        if (empty($data['settings'])) {
            return new Crash('settings 不规范');
        }
        $settings = json_decode($data['settings'], true);

        ob_start();
        $config = $this->singletonWidget(
            'Widget_Users_Profile',
            NULL,
            $settings
        );
        if ($data['option'] == 'profile') {
            $config->updateProfile();
        } else if ($data['option'] == 'options') {
            $config->updateOptions();
        } else if ($data['option'] == 'password') {
            $config->updatePassword();
//            } else if ($struct['option'] == 'personal') {
//                $config->updatePersonal();
        }
        ob_end_clean();

        return ob_get_clean();
    }

    /**
     * @param $data
     * @return string|Crash
     * @throws Crash
     */
    public function metcip_kat_setting_config($data)
    {
        $this->check_access('administrator');

        if (!isset($data['option'])) {
            return new Crash('缺少必要参数', 403);
        }

        if ($data['option'] == 'general') {
            $alias = 'Widget_Options_General';
        } else if ($data['option'] == 'discussion') {
            $alias = 'Widget_Options_Discussion';
        } else if ($data['option'] == 'reading') {
            $alias = 'Widget_Options_Reading';
        } else if ($data['option'] == 'permalink') {
            $alias = 'Widget_Options_Permalink';
        } else {
            return new Crash('option 不规范');
        }

        if ($data['method'] == 'set') {
            if (empty($this->option->allowSetting)) {
                return new Crash("已关闭基本设置能力\n可以在南博插件里开启设置能力", 202);
            }

            if (empty($data['settings'])) {
                return new Crash('settings 不规范');
            }
            $settings = json_decode($data['settings'], true);

            ob_start();
            $config = $this->singletonWidget(
                $alias,
                null,
                $settings
            );
            if ($data['option'] == 'general') {
                $config->updateGeneralSettings();
            } else if ($data['option'] == 'discussion') {
                $config->updateDiscussionSettings();
            } else if ($data['option'] == 'reading') {
                $config->updateReadingSettings();
            } else if ($data['option'] == 'permalink') {
                $config->updatePermalinkSettings();
            }
            ob_end_clean();

            return '设置已经保存';
        }

        ob_start();
        $config = $this->singletonWidget($alias);
        $form = $config->form();
        $form->setAction(NULL);
        $form->setAttribute('id', 'form');
        $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
        $form->render();

        return ob_get_clean();
    }

    /**
     * @param $data
     * @return array|Crash
     * @throws Crash
     */
    public function metcip_kat_plugins_drag($data)
    {
        $this->check_access('administrator');

        $target = $data['option'] ?: 'typecho';
        $list = [];
        $activatedPlugins = $this->singletonWidget('Widget_Plugins_List@activated', 'activated=1');

        if ($activatedPlugins->have() || !empty($activatedPlugins->activatedPlugins)) {
            while ($activatedPlugins->next()) {
                $list[$activatedPlugins->name] = array(
                    'activated' => true,
                    'name' => $activatedPlugins->name,
                    'title' => $activatedPlugins->title,
                    'dependence' => $activatedPlugins->dependence,
                    'description' => strip_tags($activatedPlugins->description),
                    'version' => $activatedPlugins->version,
                    'homepage' => $activatedPlugins->homepage,
                    'author' => $activatedPlugins->author,
                    'config' => $activatedPlugins->config
                );
            }
        }

        $deactivatedPlugins = $this->singletonWidget('Widget_Plugins_List@unactivated', 'activated=0');

        if ($deactivatedPlugins->have() || !$activatedPlugins->have()) {
            while ($deactivatedPlugins->next()) {
                $list[$deactivatedPlugins->name] = array(
                    'activated' => false,
                    'name' => $deactivatedPlugins->name,
                    'title' => $deactivatedPlugins->title,
                    'dependence' => true,
                    'description' => strip_tags($deactivatedPlugins->description),
                    'version' => $deactivatedPlugins->version,
                    'homepage' => $deactivatedPlugins->homepage,
                    'author' => $deactivatedPlugins->author,
                    'config' => false
                );
            }
        }

        if ($target == 'testore') {
            $activatedList = $this->options->plugins['activated'];
            if (isset($activatedList['TeStore'])) {
                $testore = $this->singletonWidget(
                    'TeStore_Action'
                );
                $storeList = array();
                $plugins = $testore->getPluginData();

                foreach ($plugins as $plugin) {
                    $thisPlugin = $list[$plugin['pluginName']];
                    $installed = array_key_exists($plugin['pluginName'], $list);
                    $activated = $installed ? $thisPlugin['activated'] : false;
                    $storeList[] = array(
                        'activated' => $activated,
                        'name' => $plugin['pluginName'],
                        'title' => $plugin['pluginName'],
                        'dependence' => $activated ? $thisPlugin['dependence'] : null,
                        'description' => strip_tags($plugin['desc']),
                        'version' => $plugin['version'],
                        'homepage' => $plugin['pluginUrl'],
                        'author' => strip_tags($plugin['authorHtml']),
                        'config' => $activated ? $thisPlugin['config'] : false,

                        'installed' => $installed,
                        'mark' => $plugin['mark'],
                        'zipFile' => $plugin['zipFile'],
                    );
                }

                return $storeList;
            } else {
                return new Crash('你没有安装 TeStore 插件', 301);
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
     * @return array|Crash
     * @throws Crash
     */
    public function metcip_kat_plugins_push($data)
    {
        $this->check_access('administrator');

        if (empty($this->option->allowPlugin)) {
            return new Crash("已关闭插件设置能力\n可以在南博插件里开启设置能力", 202);
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
                        return new Crash('该插件已被安装过', 401);
                    } else {
                        $testore->install();
                    }
                } else if ($data['method'] == 'deactivate') {
                    $testore->uninstall();
                }

                return Json::decode(
                    Typecho_Cookie::get('__typecho_notice'), true
                )[0];
            } else {
                return new Crash('你没有安装 TeStore 插件', 301);
            }
        } else {
            $plugins = $this->singletonWidget(
                'Widget_Plugins_Edit'
            );

            if ($data['method'] == 'activate') {
                $plugins->activate($data['pluginName']);

            } else if ($data['method'] == 'deactivate') {
                $plugins->deactivate($data['pluginName']);

            }

            return Json::decode(
                Typecho_Cookie::get('__typecho_notice'), true
            )[0];
        }
    }

    /**
     * @param $data
     * @return array|Crash
     * @throws Crash
     */
    public function metcip_kat_themes_drag($data)
    {
        $this->check_access('administrator');

        $list = [];
        $themes = $this->singletonWidget('Widget_Themes_List');

        while ($themes->next()) {
            $list[] = array(
                'activated' => $themes->activated,
                'name' => $themes->name,
                'title' => $themes->title,
                'description' => strip_tags($themes->description),
                'version' => $themes->version,
                'homepage' => $themes->homepage,
                'author' => $themes->author,
                'config' => false
            );
        }

        return $list;
    }

    /**
     * @param $data
     * @return string|Crash
     * @throws Crash
     */
    public function metcip_kat_themes_push($data)
    {
        $this->check_access('administrator');

        if (empty($this->option->allowTheme)) {
            return new Crash("已关闭主题设置能力\n可以在南博插件里开启设置能力", 202);
        }

        $themes = $this->singletonWidget(
            'Widget_Themes_Edit'
        );

        if ($data['method'] == 'changeTheme') {
            $themes->changeTheme($data['themeName']);
            return '外观已经改变';
        }

        return new Crash('未知错误');
    }
}