<?php
/**
 * @Package Kat
 * @Author 陆之岇(kraity)
 * @Studio 南博网络科技工作室
 * @GitHub https://github.com/krait-team/kat-php
 * @Version 1.0.0
 * @Description Data exchange format
 */

/**
 * @param $data
 * @param null $name
 */
function kat_response($data, $name = NULL)
{
    $kat = kat_encode($data, $name);
    header('Content-Length: ' . strlen($kat));
    header('Content-Type: text/kat');
    exit($kat);
}

/**
 * Class Crash
 */
class Crash extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable $previous
     */
    public function __construct($message = '', $code = 500, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Class KatAry
 */
class KatAry
{
    protected $alias;
    protected $value;

    /**
     * @param array|$value
     * @param null $alias
     */
    public function __construct($value = [], $alias = null)
    {
        $this->alias = $alias;
        $this->value = $value;
    }

    /**
     * @param $alias
     */
    public function alias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * @param $val
     */
    public function add($val)
    {
        $this->value[] = $val;
    }

    /**
     * @param $key
     * @param $val
     */
    public function set($key, $val)
    {
        $this->value[$key] = $val;
    }

    /**
     * @param $key
     * @param null $def
     * @return mixed|null
     */
    public function get($key, $def = null)
    {
        return isset($this->value[$key]) ?
            $this->value[$key] : $def;
    }

    /**
     * @param $key
     */
    public function del($key)
    {
        unset($this->value[$key]);
    }

    /**
     * @target clear
     */
    public function clear()
    {
        $this->alias = null;
        $this->value = [];
    }

    /**
     * @target clean
     */
    public function clean()
    {
        $this->alias = null;
        $this->value = null;
    }

    /**
     * @return null|string
     */
    public function space()
    {
        return $this->alias;
    }

    /**
     * @return array
     */
    public function entry()
    {
        return $this->value;
    }
}

/**
 * Class KatAny
 */
class KatAny
{
    protected $alias;
    protected $value;

    /**
     * @param array|$value
     * @param null $alias
     */
    public function __construct($value = [], $alias = null)
    {
        $this->alias = $alias;
        $this->value = $value;
    }

    /**
     * @param $alias
     */
    public function alias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * @param $key
     * @param $val
     */
    public function set($key, $val)
    {
        $this->value[$key] = $val;
    }

    /**
     * @param $key
     * @param null $def
     * @return mixed|null
     */
    public function get($key, $def = null)
    {
        return isset($this->value[$key]) ?
            $this->value[$key] : $def;
    }

    /**
     * @param $key
     */
    public function del($key)
    {
        unset($this->value[$key]);
    }

    /**
     * @target clear
     */
    public function clear()
    {
        $this->alias = null;
        $this->value = [];
    }

    /**
     * @target clean
     */
    public function clean()
    {
        $this->alias = null;
        $this->value = null;
    }

    /**
     * @return null|string
     */
    public function space()
    {
        return $this->alias;
    }

    /**
     * @return array
     */
    public function entry()
    {
        return $this->value;
    }
}