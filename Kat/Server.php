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
    const VERSION = '1.0.0';

    /**
     * HOOK函数
     *
     * @var array
     */
    protected $hookers = [];

    /**
     * 回调函数
     *
     * @var array
     */
    protected $methods = [];

    /**
     * 需验函数名
     *
     * @var array
     */
    protected $metcips = [];

    /**
     * Kat_Server
     *
     * @access public
     * @param $attach
     */
    public function __construct($attach = null)
    {
        if (isset($attach)) {
            $this->register($attach);
        }
    }

    /**
     * @param $attach
     */
    public function register($attach)
    {
        // register
        foreach (get_class_methods($attach) as $method) {
            // cut
            $mtd = substr(
                $method, 7
            );

            // check
            if ($mtd === false) {
                continue;
            }

            switch (substr($method, 0, 7)) {
                case 'metcip_':
                    $this->metcips[] = $mtd;
                case 'method_':
                    $this->methods[$mtd] = $method;
                    break;
                case 'hooker_':
                    $this->hookers[$mtd] = $method;
            }
        }
        unset($mtd);
    }

    /**
     * 接收
     *
     * @param $attach
     * @param $request
     * @return void
     * @throws Exception
     */
    public function receive($attach, $request)
    {
        // start
        $this->hooker(
            $attach, 'start'
        );

        try {
            // read
            $kat = kat_decode(
                $request
            );

            // check data
            if (!is_array($kat)) {
                $kat = null;
                throw new Exception(
                    'The data is empty or illegal'
                );
            }

            // check method
            $this->checkMethod(
                $kat['method']
            );

            // ready
            $this->hooker($attach,
                'ready', [&$kat]
            );

            // security
            if ($this->hasMetcip($kat['method'])) {
                // accept
                $this->hooker($attach,
                    'accept', [&$kat]
                );
            }

            // check request
            if (!is_string($kat['request'])) {
                throw new Exception(
                    'The data is not a string'
                );
            }

            // decode
            $kat['request'] = kat_decode(
                $kat['request']
            );

            // check request
            if (!is_array($kat['request'])) {
                throw new Exception(
                    'The data is illegal'
                );
            }

            // hook before
            $this->hooker($attach,
                'before', [&$kat]
            );

            // callback
            $kat['response'] = $this->callMethod([$attach,
                $this->methods[$kat['method']]
            ], $kat['request']);

            // isolate
            unset($kat['request']);

            // hook after
            $this->hooker($attach,
                'after', [&$kat]
            );

            // response
            $this->onResponse($attach,
                $kat, 'kat'
            );

            // isolate
            unset($kat['response']);

        } catch (Exception $e) {
            // check data
            if (empty($kat)) {
                // thread
                $kat = [
                    'accept' => 'kat-ins',
                    'method' => 'kat-test'
                ];
            }

            // error
            $kat['response'] = $e;

            // error
            $this->onError(
                $attach, $kat
            );
        }

        // isolate
        unset($kat, $request);
    }

    /**
     * HOOK内部方法
     *
     * @param $attach
     * @param string $method 方法名
     * @param mixed $args 参数
     * @return mixed
     * @throws Exception
     */
    protected function hooker($attach, $method, $args = [])
    {
        if ($this->hasHooker($method)) {
            return $this->callMethod([
                $attach, $this->hookers[$method]
            ], $args);
        }

        return null;
    }

    /**
     * @param $attach
     * @param $data
     * @param string $name
     * @throws Exception
     */
    protected function onResponse($attach, $data, $name = 'err')
    {
        //callback
        $callback = [
            'accept' => $data['accept'],
            'method' => $data['method'],
            'response' => kat_encode(
                $data['response']
            )
        ];

        // isolate
        unset($data['response']);

        // challenge
        if ($this->hasMetcip($data['method'])) {
            $this->hooker($attach, 'challenge', [
                $data, &$callback
            ]);
        }

        // encode
        $kat = kat_encode(
            $callback, $name
        );

        // isolate
        unset($callback);

        // hook after
        $this->hooker($attach, 'end', [&$kat, [
            'Kat' => Kat_Server::VERSION,
            'Content-Type' => 'text/kat'
        ]]);

        // isolate
        unset($data, $kat);
    }

    /**
     * @param $attach
     * @param $data
     * @throws Exception
     */
    protected function onError($attach, $data)
    {
        $this->onResponse($attach, $data,
            $data['response']->getCode() > 99 ? 'kat' : 'err'
        );
    }

    /**
     * @param array $method
     * @param mixed $args
     * @return mixed
     * @throws Exception
     */
    public function callMethod($method, $args = [])
    {
        // check
        if (is_callable($method)) {
            // hook
            return call_user_func_array(
                $method, $args
            );
        }

        throw new Exception(
            'Server error, method does not exist'
        );
    }

    /**
     * 是否存在HOOK
     *
     * @param string $method 方法名
     * @return bool
     */
    protected function hasHooker($method)
    {
        return isset($this->hookers[$method]);
    }

    /**
     * 是否存在方法
     *
     * @param string $method 方法名
     * @return bool
     */
    protected function hasMethod($method)
    {
        return isset($this->methods[$method]);
    }

    /**
     * 是否签名方法
     *
     * @param string $method 方法名
     * @return bool
     */
    protected function hasMetcip($method)
    {
        return in_array($method, $this->metcips);
    }

    /**
     * 是否合法方法
     *
     * @param string $method 方法名
     * @throws Exception
     */
    protected function checkMethod($method)
    {
        if (preg_match("/^[0-9a-zA-Z_]{4,}$/", $method)
            && $this->hasMethod($method)) {
            return;
        }

        throw new Exception(
            'Server error, method does not exist'
        );
    }
}
