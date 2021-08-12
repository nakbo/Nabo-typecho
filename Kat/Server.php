<?php
/**
 * @Package Kat
 * @Author 陆之岇(kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team/kat-php
 * @Version 1.0.0
 * @Description Remote Procedure Call
 */

/**
 * Kat_Server
 *
 * @package Kat
 */
class Kat_Server
{
    /**
     * Kat 版本号
     *
     * @var string
     */
    const VERSION = "1.0.0";

    /**
     * 数据
     *
     * @var array
     */
    protected $data;

    /**
     * HOOK函数
     *
     * @access private
     * @var array
     */
    protected $hooks = [];

    /**
     * 签名函数
     *
     * @access private
     * @var array
     */
    protected $ciphers = [];

    /**
     * 回调函数
     *
     * @access private
     * @var array
     */
    protected $methods = [];

    /**
     * 需验函数名
     *
     * @access private
     * @var array
     */
    protected $metcips = [];

    /**
     * Kat_Server
     *
     * @access public
     */
    public function __construct()
    {
        // Occupy a seat, waiting for follow-up.
    }

    /**
     * @param $attach
     */
    public function register(&$attach)
    {
        // register
        foreach (get_class_methods($attach) as $method) {
            if (($_method = substr($method, 7)) === false) {
                continue;
            }
            switch (substr($method, 0, 7)) {
                case 'metcip_':
                    $this->addMetcip($_method);
                case 'method_':
                    $this->addMethod($_method, [&$attach, $method]);
                    break;
                case 'cipher_':
                    $this->addCipher($_method, [&$attach, $method]);
                    break;
                case 'hooker_':
                    $this->addHook($_method, [&$attach, $method]);
            }
        }
        unset($_method);
    }

    /**
     * 服务入口
     *
     * @access private
     * @param mixed $request 输入参数
     * @return void
     * @throws Exception
     */
    public function launch($request = false)
    {
        if ($request === false) {
            $request = file_get_contents("php://input");
            if (!$request) {
                die('Kat server accepts POST requests only');
            }
        }

        // hook launch
        $this->hook('launch', [$request]);

        try {
            // read
            $this->data = kat_decode($request);

            // check method
            $this->checkMethod($this->data['method']);

            // hook before
            $this->hook('before', [
                $this->data['method'], &$this->data['request']
            ]);

            // security
            if ($this->hasMetcip($this->data['method'])) {
                // ready
                $this->cipher('ready', [
                    $this->data['method']
                ]);

                // access
                $this->cipher('access', [
                    $this->data['method'], &$this->data['auth']
                ]);

                // accept
                $this->cipher('accept', [
                    &$this->data['cert'], &$this->data['request']
                ]);
            }

            // reread
            $this->data['request'] = kat_decode($this->data['request']);

        } catch (Exception $e) {
            $this->onError(
                $this->data['method'], $e
            );
        }

        // callback
        try {
            $callback = $this->callnc(
                $this->data['method'],
                $this->data['request']
            );

            // response
            $this->onResponse(
                $this->data['method'],
                $callback, 'kat'
            );
        } catch (Exception $e) {
            $this->onError(
                $this->data['method'], $e
            );
        }
    }

    /**
     * 内部方法
     *
     * @access private
     * @param string $method 方法名
     * @param null $args 参数
     * @return mixed
     * @throws Exception
     */
    private function cipher($method, $args = NULL)
    {
        if ($this->hasCipher($method)) {
            return $this->callMethod(
                $this->ciphers[$method], $args
            );
        }
        return NULL;
    }

    /**
     * 呼叫内部方法
     *
     * @access private
     * @param string $method 方法名
     * @param null $args 参数
     * @return mixed
     * @throws Exception
     */
    private function callnc($method, $args = NULL)
    {
        if ($this->hasMethod($method)) {
            return $this->callMethod(
                $this->methods[$method], $args
            );
        }
        throw new Exception('Server error, method does not exist', 406);
    }

    /**
     * HOOK内部方法
     *
     * @access private
     * @param string $method 方法名
     * @param null $args 参数
     * @return mixed
     * @throws Exception
     */
    private function hook($method, $args = NULL)
    {
        if ($this->hasHook($method)) {
            return $this->callMethod(
                $this->hooks[$method], $args
            );
        }
        return NULL;
    }

    /**
     * response
     *
     * @param $method
     * @param $response
     * @param string $name
     * @throws Exception
     */
    private function onResponse($method, $response, $name = 'err')
    {
        //callback
        $callback = [
            'method' => $method,
            'response' => kat_encode($response)
        ];

        // challenge
        if ($this->hasMetcip($this->data['method'])) {
            $this->cipher('challenge', [
                $method, &$callback
            ]);
        }

        $kat = kat_encode($callback, $name);

        // hook after
        $this->hook('after', [
            $method, &$kat
        ]);

        // kat
        header('Kat: ' . Kat_Server::VERSION);
        header('Date: ' . date('r'));
        header('Connection: close');
        header('Content-Length: ' . strlen($kat));
        header('Content-Type: text/kat');
        exit($kat);
    }

    /**
     * response
     *
     * @param $method
     * @param Exception $error
     * @throws Exception
     */
    private function onError($method, $error)
    {
        $this->onResponse($method, $error,
            $error->getCode() > 100 && $error->getCode() < 1000 ? 'kat' : 'err'
        );
    }

    /**
     * @param $method
     * @param null $args
     * @return void|mixed
     * @throws Exception
     */
    public function callMethod($method, $args = NULL)
    {
        if (is_callable($method)) {
            return is_array($args) ? call_user_func_array($method, $args) : call_user_func($method);
        } else if (strpos($method, 'system')) {
            return $this->callMethod([
                &$this, substr($method, 7)
            ], $args);
        }
        throw new Exception('Server error, method does not exist', 404);
    }

    /**
     * 是否存在HOOK
     *
     * @access private
     * @param string $method 方法名
     * @return mixed
     */
    private function hasHook($method)
    {
        return array_key_exists($method, $this->hooks);
    }

    /**
     * 是否存在签名
     *
     * @access private
     * @param string $method 方法名
     * @return mixed
     */
    private function hasCipher($method)
    {
        return array_key_exists($method, $this->ciphers);
    }

    /**
     * 是否存在方法
     *
     * @access private
     * @param string $method 方法名
     * @return mixed
     */
    private function hasMethod($method)
    {
        return array_key_exists($method, $this->methods);
    }

    /**
     * 是否签名方法
     *
     * @access private
     * @param string $method 方法名
     * @return mixed
     */
    private function hasMetcip($method)
    {
        return in_array($method, $this->metcips);
    }

    /**
     * 是否合法方法
     *
     * @access private
     * @param string $method 方法名
     * @throws Exception
     */
    private function checkMethod($method)
    {
        if (preg_match("/^[0-9a-zA-Z_]{4,}$/", $method) && $this->hasMethod($method)) {
            return;
        }
        throw new Exception('Server error, method does not exist', 404);
    }

    /**
     * 添加回调
     *
     * @param $name
     * @param $method
     */
    public function addMethod($name, $method)
    {
        $this->methods[$name] = $method;
    }

    /**
     * @param $methods
     */
    public function addMethods($methods)
    {
        $this->methods = array_merge($this->methods, $methods);
    }

    /**
     * @param $method
     */
    public function addMetcip($method)
    {
        $this->metcips[] = $method;
    }

    /**
     * @param $methods
     */
    public function addMetcips($methods)
    {
        $this->metcips = array_merge($this->metcips, $methods);
    }

    /**
     * @param $name
     * @param $method
     */
    public function addCipher($name, $method)
    {
        $this->ciphers[$name] = $method;
    }

    /**
     * @param $ciphers
     */
    public function addCiphers($ciphers)
    {
        $this->ciphers = array_merge($this->ciphers, $ciphers);
    }

    /**
     * @param $ciphers
     */
    public function setCiphers($ciphers)
    {
        $this->ciphers = $ciphers;
    }

    /**
     * @param $hook
     * @param $method
     */
    public function addHook($hook, $method)
    {
        $this->hooks[$hook] = $method;
    }

    /**
     * @param $hooks
     */
    public function addHooks($hooks)
    {
        $this->hooks = array_merge($this->hooks, $hooks);
    }

    /**
     * @param $hooks
     */
    public function setHooks($hooks)
    {
        $this->hooks = $hooks;
    }
}
