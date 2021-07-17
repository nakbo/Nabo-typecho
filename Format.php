<?php

class Nabo_Format
{
    /**
     * @param $keywords
     * @return string
     */
    public static function searchOf($keywords)
    {
        return '%' . str_replace(' ', '%', $keywords) . '%';
    }

    /**
     * @param string $def
     * @return bool
     */
    public static function successOf($def = 'notice')
    {
        return Typecho_Cookie::get('__typecho_notice_type', $def) == 'success';
    }

    /**
     * @param $data
     * @param $page
     * @param $size
     */
    public static function pagingOf($data, &$page, &$size)
    {
        $size = intval($data['number']);
        $size = $size > 0 ? $size : 10;
        $offset = intval($data['offset']);
        $page = $offset > 0 ? ceil($offset / $size) : 1;
    }

    /**
     * @param $user
     * @param false $token
     * @return array
     */
    public static function userOf($user, $token = false)
    {
        $_user = array(
            'site' => (string)$user['url'],
            'uid' => (int)$user['uid'],
            'name' => (string)$user['name'],
            'mail' => (string)$user['mail'],
            'nickname' => (string)$user['screenName'],
            'group' => (string)$user['group'],
            'access' => (string)$user['authCode'],
            'logged' => (int)$user['logged'],
            'created' => (int)$user['created'],
            'touched' => (int)$user['activated']
        );
        if ($token) {
            $_user['token'] = $token;
        }
        return $_user;
    }

    /**
     * @param  $server
     * @param $notes
     * @return array
     */
    public static function notesOf($server, $notes)
    {
        $list = [];
        foreach ($notes as $note) {
            $list[] = self::noteOf(
                $server->filter($note)
            );
        }
        return $list;
    }

    /**
     * @param $note
     * @return array
     */
    public static function noteOf($note)
    {
        $db = Typecho_Db::get();
        $nid = intval($note['cid']);

        // fields
        $fields = [];
        foreach ($db->fetchAll($db->select()
            ->from('table.fields')->where('cid = ?', $nid)) as $row) {
            $fields[] = [
                'type' => $row['type'],
                'key' => $row['name'],
                'val' => $row[$row['type'] . '_value']
            ];
        }
        unset($row);

        // categories
        $categories = [];
        foreach ($note['categories'] as $row) {
            $categories[] = $row['name'];
        }
        unset($row);

        // tags
        $tags = [];
        foreach ($db->fetchAll($db->select()->from('table.metas')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $nid)->where('table.metas.type = ?', 'tag')
            ->order('table.metas.order')) as $row) {
            $tags[] = $row['name'];
        }
        unset($row);

        return array(
            'nid' => $nid,
            'uid' => (int)$note['authorId'],
            'oid' => (int)$note['parent'],
            'title' => (string)$note['title'],
            'content' => (string)$note['text'],

            'type' => (string)$note['type'],
            'slug' => (string)$note['slug'],
            'order' => (int)$note['order'],
            'status' => (string)$note['status'],

            'password' => (string)$note['password'],
            'template' => (string)$note['template'],
            'permalink' => (string)$note['permalink'],

            'fields' => $fields,
            'tags' => $tags,
            'categories' => $categories,

            'allowPing' => (int)$note['allowPing'],
            'allowFeed' => (int)$note['allowFeed'],
            'allowComment' => (int)$note['allowComment'],

            'created' => (int)$note['created'],
            'modified' => (int)$note['modified'],
        );
    }

    /**
     * @param $discusses
     * @return array
     */
    public static function discussesOf($discusses)
    {
        $list = [];
        foreach ($discusses as $discuss) {
            $list[] = self::discussOf($discuss);
        }
        return $list;
    }

    /**
     * @param $discuss
     * @return array
     */
    public static function discussOf($discuss)
    {
        return array(
            'did' => (int)$discuss['coid'],
            'nid' => (int)$discuss['cid'],
            'uid' => (int)$discuss['authorId'],

            'nickname' => (string)$discuss['author'],
            'mail' => (string)$discuss['mail'],
            'site' => (string)$discuss['url'],
            'message' => (string)$discuss['text'],

            'status' => (string)$discuss['status'],
            'agent' => (string)$discuss['agent'],
            'address' => (string)$discuss['ip'],

            '_nid' => (int)$discuss['parent'],
            '_title' => (string)$discuss['title'],

            'created' => (int)$discuss['created'],
        );
    }

    /**
     * @param $metas
     * @return array
     */
    public static function metasOf($metas)
    {
        $list = [];
        foreach ($metas as $meta) {
            $list[] = self::metaOf($meta);
        }
        return $list;
    }

    /**
     * @param $meta
     * @return array
     */
    public static function metaOf($meta)
    {
        return array(
            'mid' => (int)$meta['mid'],
            'oid' => (int)$meta['parent'],
            'type' => (string)$meta['type'],
            'name' => (string)$meta['name'],
            'slug' => (string)$meta['slug'],
            'desc' => (string)$meta['description'],
            'order' => (int)$meta['order'],
            'count' => (int)$meta['count'],
        );
    }

    /**
     * @param $medias
     * @return array
     */
    public static function mediasOf($medias)
    {
        $list = [];
        foreach ($medias as $media) {
            $list[] = self::mediaOf($media);
        }
        return $list;
    }

    /**
     * @param $media
     * @return array
     */
    public static function mediaOf($media)
    {
        $media['attachment'] = new Typecho_Config(@unserialize($media['text']));
        return array(
            'mid' => (int)$media['cid'],
            'oid' => (int)$media['parent'],
            'title' => (string)$media['title'],
            'message' => (string)$media['attachment']->description,
            'slug' => (string)$media['slug'],
            'size' => (int)$media['attachment']->size,
            'link' => (string)Widget_Upload::attachmentHandle($media),
            'path' => (string)$media['attachment']->path,
            'mime' => (string)$media['attachment']->mime,
            'created' => (int)$media['created'],
            'modified' => (int)$media['modified']
        );
    }

    /**
     * @param $dynamics
     * @return array
     */
    public static function dynamicsOf($dynamics)
    {
        $list = [];
        foreach ($dynamics as $dynamic) {
            $list[] = self::dynamicOf($dynamic);
        }
        return $list;
    }

    /**
     * @param $dynamic
     * @return array
     */
    public static function dynamicOf($dynamic)
    {
        return array(
            'did' => (int)$dynamic['did'],
            'authorId' => (int)$dynamic['authorId'],
            'title' => (string)$dynamic['title'],
            'text' => (string)$dynamic['text'],
            'status' => (string)$dynamic['status'],
            'agent' => (string)$dynamic['agent'],
            'created' => (int)$dynamic['created'],
            'modified' => (int)$dynamic['modified'],
            'permalink' => (string)$dynamic['permalink'],
        );
    }

    /**
     * @param $cid
     * @return false|string
     */
    public static function fieldsOf($cid)
    {
        $fields = [];
        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()
            ->from('table.fields')
            ->where('cid = ?', $cid));
        foreach ($rows as $row) {
            $fields[] = array(
                "name" => $row['name'],
                "type" => $row['type'],
                "value" => $row[$row['type'] . '_value']
            );
        }
        return json_encode($fields, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param $uid
     * @param $from
     * @param $type
     * @return int
     */
    public static function wordsSizeOf($uid, $from, $type)
    {
        $chars = 0;
        $owner = "table.comments" == $from ? "ownerId" : "authorId";
        $db = Typecho_Db::get();
        $select = $db->select('text')
            ->from($from)
            ->where($owner . ' = ?', $uid)
            ->where('type = ?', $type);
        $rows = $db->fetchAll($select);
        foreach ($rows as $row) {
            $chars += mb_strlen($row['text'], 'UTF-8');
        }
        return $chars;
    }
}