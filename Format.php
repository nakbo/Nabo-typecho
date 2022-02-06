<?php

class Nabo_Format
{
    /**
     * @param $status
     * @return string
     */
    public static function note_status($status)
    {
        switch ($status) {
            case 'open':
                return 'publish';
            case 'self':
                return 'private';
            case 'hide':
                return 'hidden';
            case 'close':
                return 'waiting';
        }
        return 'publish';
    }

    /**
     * @param $status
     * @return string
     */
    public static function note_status_by($status)
    {
        switch ($status) {
            case 'publish':
                return 'open';
            case 'private':
                return 'self';
            case 'hidden':
                return 'hide';
            case 'waiting':
                return 'close';
        }
        return 'open';
    }

    /**
     * @param $status
     * @return string
     */
    public static function dynamic_status($status)
    {
        switch ($status) {
            case 'open':
                return 'publish';
            case 'self':
                return 'private';
            case 'hide':
                return 'hidden';
        }
        return 'publish';
    }

    /**
     * @param $status
     * @return string
     */
    public static function dynamic_status_by($status)
    {
        switch ($status) {
            case 'publish':
                return 'open';
            case 'private':
                return 'self';
            case 'hidden':
                return 'hide';
        }
        return 'open';
    }

    /**
     * @param $status
     * @return string
     */
    public static function discuss_status($status)
    {
        switch ($status) {
            case 'open':
                return 'approved';
            case 'spam':
                return 'spam';
            case 'close':
                return 'waiting';
        }
        return 'approved';
    }

    /**
     * @param $status
     * @return string
     */
    public static function discuss_status_by($status)
    {
        switch ($status) {
            case 'approved':
                return 'open';
            case 'spam':
                return 'spam';
            case 'waiting':
                return 'close';
        }
        return 'open';
    }

    /**
     * @param $keywords
     * @return string
     */
    public static function search($keywords)
    {
        return '%' . str_replace(' ', '%', $keywords) . '%';
    }

    /**
     * @param string $def
     * @return bool
     */
    public static function success($def = 'notice')
    {
        return Typecho_Cookie::get('__typecho_notice_type', $def) == 'success';
    }

    /**
     * @param $data
     * @param $page
     * @param $size
     */
    public static function paging($data, &$page, &$size)
    {
        $size = intval($data['length']);
        $size = $size > 0 ? $size : 10;

        $offset = intval($data['offset']);
        $page = $offset > 0 ? ceil($offset / $size) : 1;
    }

    /**
     * @param $widget
     * @param null $last
     * @return mixed
     */
    public static function lastOf($widget, $last = NULL)
    {
        while (($row = $widget->next()) !== false) {
            $last = &$row;
        }
        return $last;
    }

    /**
     * @param $user
     * @param false $token
     * @return array
     */
    public static function user($user, $token = false)
    {
        $target = array(
            'uid' => (int)$user['uid'],
            'name' => (string)$user['name'],
            'mail' => (string)$user['mail'],
            'nickname' => (string)$user['screenName'],
            'site' => (string)$user['url'],
            'role' => (string)$user['group'],
            'access' => (string)$user['authCode'],
            'touched' => (int)$user['activated'],
            'created' => (int)$user['created'],
            'modified' => (int)$user['logged'],
        );
        if ($token) {
            $target['token'] = $token;
        }
        return $target;
    }

    /**
     * @param $notes
     * @param bool $more
     * @return KatAry
     */
    public static function notes($notes, $more = false)
    {
        $arg = new KatAry();
        foreach ($notes as $note) {
            $arg->add(
                self::note(
                    $note, $more
                )
            );
        }
        return $arg;
    }

    /**
     * @param $note
     * @param bool $more
     * @return KatAny
     */
    public static function note($note, $more = true)
    {
        $db = Typecho_Db::get();
        $nid = intval($note['cid']);

        // slug
        $slug = $note['slug'];
        $note['slug'] = urlencode($slug);

        // fields
        $fields = [];
        foreach ($db->fetchAll($db->select()
            ->from('table.fields')->where('cid = ?', $nid)) as $row) {
            $fields[] = [
                't' => $row['type'],
                'k' => $row['name'],
                'v' => $row[$row['type'] . '_value']
            ];
        }
        unset($row);

        // metas
        $metas = $db->fetchAll($db->select()->from('table.metas')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $note['cid'])
            ->order('table.metas.order')
        );

        $tags = [];
        $note['categories'] = [];
        foreach ($metas as $row) {
            if ($row['type'] == 'category') {
                $note['categories'][] = $row;
            } else {
                $tags[] = $row['name'];
            }
        }
        $tags = implode(',', $tags);
        unset($row);

        $meta = 0;
        $category = '';
        if (empty($note['categories'])) {
            $note['category'] = NULL;
        } else {
            list($cate) = $note['categories'];
            $meta = $cate['mid'];
            $category = (string)$cate['name'];
            $note['category'] = urlencode(
                $cate['slug']
            );
            unset($cate);
        }

        // date
        $timestamp = $note['created'] + Typecho_Date::$timezoneOffset
            - Typecho_Date::$serverTimezoneOffset;
        $note['year'] = date('Y', $timestamp);
        $note['month'] = date('m', $timestamp);
        $note['day'] = date('d', $timestamp);

        // permalink
        $pathinfo = Typecho_Router::get($note['type']) ?
            Typecho_Router::url($note['type'], $note) : '#';
        $permalink = Typecho_Common::url($pathinfo, Helper::options()->index);

        // content
        if ($more && isset($note['text'])) {
            if (strpos($note['text'], '<!--markdown-->') === 0) {
                $note['text'] = substr($note['text'], 15);
            }
        } else {
            unset($note['text']);
        }

        $type = (string)$note['type'];
        $code = (string)$note['password'];
        $status = (string)$note['status'];
        switch ($type) {
            case 'post_draft':
            {
                $type = 'post';
                $status = 'draft';
                break;
            }
            case 'page_draft':
            {
                $type = 'page';
                $status = 'draft';
                break;
            }
            default:
            {
                if (empty($code)) {
                    $status = self::note_status_by($status);
                } else {
                    $status = 'code';
                }
            }
        }

        return new KatAny([
            'nid' => $nid,
            'uid' => (int)$note['authorId'],
            'title' => (string)$note['title'],
            'content' => (string)$note['text'],
            'type' => $type,
            'slug' => (string)$slug,
            'code' => $code,
            'rely' => (int)$note['parent'],
            'meta' => $meta,
            'tags' => $tags,
            'order' => (int)$note['order'],
            'envoy' => (string)$note['template'],
            'status' => $status,
            'extras' => $fields,
            'category' => $category,
            'permalink' => (string)$permalink,
            'created' => (int)$note['created'],
            'modified' => (int)$note['modified'],

            'allowPing' => (int)$note['allowPing'],
            'allowFeed' => (int)$note['allowFeed'],
            'allowDisc' => (int)$note['allowComment']
        ], 'Note');
    }

    /**
     * @param $discuses
     * @return KatAry
     */
    public static function discuses($discuses)
    {
        $ary = new KatAry();
        foreach ($discuses as $discuss) {
            $ary->add(
                self::discuss($discuss)
            );
        }
        return $ary;
    }

    /**
     * @param $discuss
     * @return KatAny
     */
    public static function discuss($discuss)
    {
        return new KatAny([
            'did' => (int)$discuss['coid'],
            'nid' => (int)$discuss['cid'],
            'uid' => (int)$discuss['authorId'],
            'mail' => (string)$discuss['mail'],
            'site' => (string)$discuss['url'],
            'author' => (string)$discuss['author'],
            'message' => (string)$discuss['text'],
            'status' => self::discuss_status_by($discuss['status']),
            'agent' => (string)$discuss['agent'],
            'address' => (string)$discuss['ip'],
            'rely' => (int)$discuss['parent'],
            'title' => (string)$discuss['title'],
            'created' => (int)$discuss['created'],
            'modified' => (int)$discuss['modified']
        ], 'Discuss');
    }

    /**
     * @param $metas
     * @return KatAry
     */
    public static function metas($metas)
    {
        $ary = new KatAry();
        foreach ($metas as $meta) {
            $ary->add(
                self::meta($meta)
            );
        }
        return $ary;
    }

    /**
     * @param $meta
     * @return KatAny
     */
    public static function meta($meta)
    {
        return new KatAny([
            'mid' => (int)$meta['mid'],
            'name' => (string)$meta['name'],
            'slug' => (string)$meta['slug'],
            'rely' => (int)$meta['parent'],
            'order' => (int)$meta['order']
        ], 'Meta');
    }

    /**
     * @param $medias
     * @return KatAry
     */
    public static function medias($medias)
    {
        $ary = new KatAry();
        foreach ($medias as $media) {
            $ary->add(
                self::media($media)
            );
        }
        return $ary;
    }

    /**
     * @param $media
     * @return KatAny
     */
    public static function media($media)
    {
        $media['attachment'] = new Typecho_Config(
            @unserialize($media['text'])
        );
        return new KatAny([
            'mid' => (int)$media['cid'],
            'name' => (string)$media['title'],
            'link' => (string)Widget_Upload::attachmentHandle($media),
            'path' => (string)$media['attachment']->path,
            'mime' => (string)$media['attachment']->mime,
            'length' => (int)$media['attachment']->size,
            'created' => (int)$media['created'],
            'modified' => (int)$media['modified']
        ], 'Media');
    }

    /**
     * @param $dynamics
     * @return KatAry
     */
    public static function dynamics($dynamics)
    {
        $ary = new KatAry();
        foreach ($dynamics as $dynamic) {
            $ary->add(
                self::dynamic($dynamic)
            );
        }
        return $ary;
    }

    /**
     * @param $dynamic
     * @return KatAny
     */
    public static function dynamic($dynamic)
    {
        return new KatAny([
            'did' => (int)$dynamic['did'],
            'uid' => (int)$dynamic['authorId'],
            'title' => (string)$dynamic['title'],
            'content' => (string)$dynamic['text'],
            'status' => Nabo_Format::dynamic_status_by(
                (string)$dynamic['status']
            ),
            'created' => (int)$dynamic['created'],
            'modified' => (int)$dynamic['modified'],
            'permalink' => (string)$dynamic['permalink'],
        ], 'Dynamic');
    }

    /**
     * @param $friends
     * @return KatAry
     */
    public static function friends($friends)
    {
        $ary = new KatAry();
        foreach ($friends as $friend) {
            $ary->add(
                self::friend($friend)
            );
        }
        return $ary;
    }

    /**
     * @param $friend
     * @return KatAny
     */
    public static function friend($friend)
    {
        return new KatAny([
            'fid' => (int)$friend['lid'],
            'name' => (string)$friend['name'],
            'link' => (string)$friend['url'],
            'image' => (string)$friend['image'],
            'intro' => (string)$friend['description'],
            'team' => (string)$friend['sort'],
            'order' => (int)$friend['order'],
            'extra' => (string)$friend['user'],
        ], 'Friend');
    }

    /**
     * @param $uid
     * @return int
     */
    public static function create_words($uid)
    {
        $count = 0;
        $db = Typecho_Db::get();
        $driver = $db->getAdapterName();

        if (strpos($driver, 'Mysql') !== false) {
            $select = $db->select('SUM(char_length(title) + char_length(text)) AS creative')
                ->from('table.contents')->where('authorId = ?', $uid);
            foreach ($db->fetchAll($select) as $row) {
                $count += $row['creative'];
            }
        } else if (strpos($driver, 'SQLite') !== false) {
            $select = $db->select('title', 'text')
                ->from('table.contents')
                ->where('authorId = ?', $uid);
            foreach ($db->fetchAll($select) as $row) {
                $count += mb_strlen($row['title'], 'UTF-8');
                $count += mb_strlen($row['text'], 'UTF-8');
            }
        }
        return $count;
    }
}