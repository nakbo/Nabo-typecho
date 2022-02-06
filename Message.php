<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 消息组件
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
 */
class Nabo_Message extends Typecho_Widget
{

    const PUSH_API = "https://a2.n.krait.cn/v1/push";

    /**
     * @param $id
     * @param $data
     * @param bool $respond
     * @return bool
     */
    public static function send($id, $data, $respond = true)
    {
        $db = Typecho_Db::get();

        $user = $db->fetchRow(
            $db->select()->from('table.nabo')
                ->where($id < 100001 ? 'uid = ?' : 'uin = ?', $id)
        );

        // check
        if (empty($user)) {
            return false;
        }

        // allow
        if (empty($user['allowPush'])) {
            return false;
        }

        // check
        if (empty($user['uin']) ||
            empty($data['title']) ||
            empty($data['message']) ||
            strlen($user['pushKey']) < 32) {
            return false;
        }

        // client
        if (($client = Typecho_Http_Client::get()) === false) {
            return false;
        }

        // push
        try {
            $client->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT'])
                ->setTimeout(5)
                ->setData([
                    'uin' => $user['uin'],
                    'key' => $user['pushKey'],
                    'author' => $data['author'],
                    'title' => $data['title'],
                    'message' => $data['message'],
                    'notice' => $data['notice'],
                    'summary' => $data['summary']
                ])->send(Nabo_Message::PUSH_API);

            if (($code = $client->getResponseStatus()) < 200 || $code > 299) {
                return false;
            }

            if ($respond) {
                return $client->getResponseBody();
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     */
    public static function loginSucceed()
    {
        $options = Helper::options();
        if (empty($options->plugin('Nabo')->allowHintLog)) {
            return;
        }
        $thiz = func_get_arg(0);
        $date = date('H点i分', time());

        $request = Typecho_Request::getInstance();
        $ip = $request->getIp();
        $ua = $request->getAgent();

        Nabo_Message::send($thiz->uid, [
            'title' => '账号登录提醒',
            'author' => $thiz->screenName,
            'message' => "您的账号 (账号ID: {$thiz->uid} 昵称: {$thiz->screenName}) 在 {$date} 已成功登录到博客后台。\n\n" .
                "独立博客: {$options->title} \n" .
                "博客地址: {$options->siteUrl} \n" .
                "IP地址: {$ip} \n" .
                "UA代理: {$ua}\n\n" .
                "本通知仅作为安全提醒，如是您本人登录请忽略即可。\n\n" .
                "如果不是您本人操作，说明您的账号已经被盗，请立即修改密码。",
            'notice' => '账号登录提醒',
            'summary' => '您的账号在' . $date . '成功登录到博客后台'
        ], false);
    }

    /**
     * @param $comment
     * @throws Typecho_Db_Exception
     */
    public static function finishComment($comment)
    {
        if ($comment->authorId != $comment->ownerId) {
            Nabo_Message::send($comment->ownerId, [
                'author' => $comment->author,
                'title' => $comment->title,
                'message' => $comment->text,
                'notice' => '你有一条新' . ($comment->status == 'approved' ? '' : '待审核的') . '评论',
                'summary' => $comment->author . '在' . date('H点i分', $comment->created) . '给你留了言'
            ], false);
        }
    }
}
