<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 用户组件
 *
 * @Author 陆之岇(Kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team
 */
class Nabo_User extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * @var array
     */
    public $identity = [];

    /**
     * @var int
     */
    public $uid;

    /**
     * @var array
     */
    public $user;

    /**
     * @var Typecho_Db
     */
    protected $db;

    /**
     * @var Widget_User
     */
    protected $widget;

    /**
     * @var Widget_Options
     */
    protected $options;

    /**
     * @var Typecho_Config
     */
    protected $option;

    const ALGO_RSA_SHA256_TS = 'RSA/SHA256/TS';
    const ALGO_LIST = [
        Nabo_User::ALGO_RSA_SHA256_TS
    ];

    /**
     * @param $request
     * @param $response
     * @param null $params
     */
    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
        $this->options = Typecho_Widget::widget('Widget_Options');
        $this->option = $this->options->plugin('Nabo');
    }

    /**
     * @param $identity
     * @throws Crash
     */
    public function identity($identity)
    {
        // uin
        $identity['uin'] = (int)$identity['uin'];

        // check
        if ($identity['uin'] < 100001) {
            throw new Crash(
                '无效南博号', 102
            );
        }

        // identity
        $this->identity = $identity;
    }

    /**
     * @return string
     */
    public function uin()
    {
        return $this->identity['uin'];
    }


    /**
     * @return string
     */
    public function name()
    {
        return $this->identity['name'];
    }

    /**
     * @return string
     */
    public function token()
    {
        return $this->identity['token'];
    }

    /**
     * @throws Crash
     * @throws ReflectionException
     */
    public function register()
    {
        // name
        $name = $this->name();

        // check
        if (empty($name)) {
            throw new Crash(
                '无效用户名', 102
            );
        }

        // find
        $user = $this->db->fetchRow($this->db->select()
            ->from('table.nabo')
            ->join('table.users', 'table.nabo.uid = table.users.uid', Typecho_Db::LEFT_JOIN)
            ->where('table.users.name = ?', $name)->limit(1));

        // check empty
        if (empty($user)) {
            // sleep
            $this->sleep();
            throw new Crash(
                '关联的用户不存在', 101
            );
        }

        // accept
        if ($this->accept($user)) {
            $this->combine($user);
            return;
        }

        // sleep
        $this->sleep();

        throw new Crash(
            '令牌错误或者签名错误', 101
        );
    }

    /**
     * 权限挑战
     *
     * @param $user
     * @return bool
     */
    public function accept($user)
    {
        // key login
        if ($user['allowCert']) {
            if ($certKey = $user['certKey']) {
                // token
                list($algo, $touch, $sign) = explode(
                    ' ', $this->token()
                );

                // ALGO TOUCH SIGN
                if (in_array(
                    $algo, Nabo_User::ALGO_LIST
                )) {
                    if (hash_equals(
                        $algo, Nabo_User::ALGO_RSA_SHA256_TS
                    )) {
                        if (time() > $touch + 16) {
                            return false;
                        }
                        return openssl_verify(
                                $touch, base64_decode($sign),
                                $certKey, OPENSSL_ALGO_SHA256
                            ) > 0;
                    }
                    return false;
                }
                if ($this->option->allowMultivia == 0) {
                    return false;
                }
            }
        }
        return hash_equals(
            $user['token'], $this->token()
        );
    }

    /**
     * @param $user
     * @throws ReflectionException
     */
    protected function combine($user)
    {
        // uid
        $this->uid = $user['uid'];

        // widget user
        $widget_user = Typecho_Widget::widget('Widget_User');

        // merge
        $this->user = $user;

        // check
        if (method_exists($widget_user, 'commitLogin')) {
            $widget_user->simpleLogin($user);
        } else {
            $widget_user->push($user);

            // reflection
            $ref = new ReflectionClass($widget_user);
            $property_user = $ref->getProperty('_user');
            $property_log = $ref->getProperty('_hasLogin');

            // accessible
            $property_user->setAccessible(true);
            $property_log->setAccessible(true);

            // set value
            $property_user->setValue($widget_user, $user);
            $property_log->setValue($widget_user, true);
        }
    }

    /**
     * @param $data
     * @return array|Crash
     */
    public function login($data)
    {
        // data
        $name = $data['name'];
        $password = $data['password'];

        $target = $this->db->fetchRow($this->db->select()
            ->from('table.users')
            ->where((strpos($name, '@') ? 'mail' : 'name') . ' = ?', $name)->limit(1));

        // check empty
        if (empty($target)) {
            // sleep
            $this->sleep();
            return new Crash(
                '用户不存在', 102
            );
        }

        if (preg_match("/^[a-f0-9]{32}$/", $password)) {
            if (hash_equals($password, md5($target['password'])) === false) {
                return new Crash(
                    '安全密码错误', 102
                );
            }
        } else {
            if ('$P$' == substr($target['password'], 0, 3)) {
                $hasher = new PasswordHash(8, true);
                $hashValidate = $hasher->CheckPassword(
                    $password, $target['password']
                );
            } else {
                $hashValidate = Typecho_Common::hashValidate(
                    $password, $target['password']
                );
            }
            if ($hashValidate === false) {
                return new Crash(
                    '密码错误', 102
                );
            }
        }

        $person = array(
            'uin' => $this->uin(),
            'token' => $token = Typecho_Common::randString(32)
        );

        if (empty($this->db->fetchRow($this->db->select()
            ->from('table.nabo')
            ->where('uid = ?', $uid = $target['uid'])))) {
            $person['uid'] = $uid;

            $this->db->query($this->db
                ->insert('table.nabo')
                ->rows($person));
        } else {
            $this->db->query($this->db
                ->update('table.nabo')
                ->rows($person)->where('uid = ?', $uid));
        }

        return $this->response(
            $target, $token
        );
    }

    /**
     * @param $credential
     * @return array|Crash
     */
    public function challenge($credential)
    {
        // check
        if (!is_array($user = $credential['user'])
            || empty($uid = (int)$user['uid'])) {
            return new Crash(
                '用户不存在', 101
            );
        }
        if (($uin = (int)$user['uin']) < 100001) {
            return new Crash(
                '非法南博号', 103
            );
        }

        // check timestamp
        if (time() > ($timestamp = $credential['timestamp']) + 32) {
            return new Crash(
                '过期二维码, 请刷新二维码重新扫码', 103
            );
        }

        if (!hash_equals(substr($challenge = $credential['challenge'], 8),
            substr(md5($this->options->secret . $timestamp), 24))) {
            return new Crash(
                '初次挑战失败', 101
            );
        }

        // check openssl_open
        if (!function_exists('openssl_open')) {
            return new Crash(
                '未安装 OpenSSL 拓展', 404
            );
        }

        $target = $this->db->fetchRow($this->db->select()
            ->from('table.users')
            ->where('uid = ?', $uid)->limit(1));

        if (empty($target)) {
            return new Crash(
                '用户不存在', 403
            );
        }

        if (!hash_equals(substr($challenge, 0, 8),
            substr(md5($target['authCode'] . $timestamp), 24))) {
            return new Crash(
                '挑战失败', 101
            );
        }

        $keyCred = $credential['keyCred'];
        if (!in_array($keyCred['algo'], Nabo_User::ALGO_LIST)) {
            return new Crash(
                '南博不支持当前博客签名算法', 104
            );
        }

        if (empty($keyCred['certKey'])) {
            return new Crash(
                '公匙不存在', 104
            );
        }

        // certKey
        $certKey = "-----BEGIN PUBLIC KEY-----\n" .
            trim($keyCred['certKey']) .
            "\n-----END PUBLIC KEY-----";

        // verify
        if (openssl_verify($challenge, base64_decode($credential['signature']),
                $certKey, OPENSSL_ALGO_SHA256) < 1) {
            return new Crash(
                '挑战解密失败, 独立博客端不支持南博端的算法' . "\n" . $certKey, 104
            );
        }

        $person = array(
            'uid' => $uid,
            'uin' => $uin,
            'token' => $token = Typecho_Common::randString(32),
            'certKey' => $certKey,
            'allowCert' => 1
        );

        if (empty($member = $this->db->fetchRow($this->db->select()
            ->from('table.nabo')->where('uid = ?', $uid)))) {
            $this->db->query(
                $this->db->insert('table.nabo')->rows($person)
            );
        } else {
            // check
            if ($member['allowCert'] == 1) {
                return new Crash(
                    '当前用户已绑定, 如需改绑请先关闭密钥登录', 103
                );
            }
            unset($person['uid']);

            $this->db->query($this->db
                ->update('table.nabo')->rows($person)
                ->where('uid = ?', $uid));
        }

        return $this->response(
            $target, $token
        );
    }

    /**
     * @param int $seconds
     */
    public function sleep($seconds = 6)
    {
        if ($this->option->allowSleep) {
            sleep($seconds);
        }
    }

    /**
     * @param $user
     * @param $token
     * @return array
     */
    public function response($user, $token = false)
    {
        return array(
            'user' => Nabo_Format::user(
                $user, $token
            ),
            'config' => [
                'title' => $this->options->title,
                'describe' => $this->options->description
            ]
        );
    }

    /**
     * @return string
     */
    public static function bindUrl()
    {
        return Typecho_Common::url(
            Nabo_Plugin::ROUTE_USER . '?do=code', Helper::options()->index
        );
    }

    /**
     * @target code
     */
    public function targetCode()
    {
        Typecho_Widget::widget('Widget_User')->to($this->widget);
        $this->widget->pass('subscriber');

        if (!function_exists('openssl_open')) {
            throw new Typecho_Exception(
                '当前环境不支持openssl_open'
            );
        }

        $user = $this->db->fetchRow($this->db->select()
            ->from('table.users')
            ->join('table.nabo', 'table.users.uid = table.nabo.uid', Typecho_Db::LEFT_JOIN)
            ->where('table.users.uid = ?', $this->widget->uid)->limit(1));

        if (empty($user)) {
            throw new Typecho_Exception(
                '未找到登录信息'
            );
        }

        $content = false;
        if (empty($user['allowCert'])) {
            $timestamp = time() + 32;
            $secret = $this->options->secret;
            $token = $user['authCode'];

            $siteUrl = trim($this->options->siteUrl);
            $secure = strpos($siteUrl, 'https://') === 0;
            $domain = rtrim(substr($siteUrl, $secure ? 8 : 7), '/');
            $rewrite = (boolean)$this->options->rewrite;
            $challenge = substr(md5($token . $timestamp), 24)
                . substr(md5($secret . $timestamp), 24);

            $content = kat_encode([
                'c' => $challenge,
                'e' => 't',
                'i' => (int)$this->widget->uid,
                'r' => [
                    'd' => $domain,
                    's' => $secure,
                    'r' => $rewrite
                ],
                't' => (int)$timestamp
            ]);
        }

        echo <<<EOF
<!DOCTYPE html>
<html lang="zh">
<head>
    <title>二维码扫码登录</title>
</head>
<body>
EOF;
        if ($content) {
            echo <<<EOF
<div class="qrcode"></div>
<div class="tip">使用南博APP 进行扫码登录</div>
<div class="fill"></div>
<script src="https://cdnjs.loli.net/ajax/libs/jquery/3.1.0/jquery.js"></script>
<script type="text/javascript" src="https://cdnjs.loli.net/ajax/libs/jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script>
        jQuery(function () {
            jQuery('.qrcode').qrcode({
            render: "canvas",
            width: 300,
            height: 300,
            text: '{$content}'
        });
    });
</script>
EOF;
        } else {
            echo <<<EOF
<div class="tip">当前用户已绑定, 如需改绑请先关闭密钥登录</div>
<div class="fill"></div>
EOF;
        }
        echo <<<EOF
<style>
    .fill {
        background: #0e0e0e;
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        right: 0;
    }
    .tip{
        color: #73664d;
        z-index: 999;
        position: absolute;
        left: 50%;
        bottom: 10%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 1em 2em;
        border-radius: 30px;
    }

    .qrcode {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 300px;
        height: 300px;
        background: white;
        z-index: 999;
        padding: 2em;
    }
</style>
</body>
</html>
EOF;
    }

    /**
     * 初始化函数
     */
    public function action()
    {
        $this->on($this->request->is('do=code'))->targetCode();
    }
}