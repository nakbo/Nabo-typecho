<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Nabo_Helper
{
    /**
     * @var array
     */
    protected $_method = [];

    /**
     * @var array
     */
    protected $_temporary = [];

    /**
     *
     * @access public
     * @param string $name 方法名
     * @param array $args 参数列表
     * @return void|mixed
     */
    public function __call($name, $args)
    {
        if (isset($this->_method[$name])) {
            return call_user_func_array($this->_method[$name], $args);
        }
        $this->_temporary[$name][] = $args;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function extract($name)
    {
        return array_pop($this->_temporary[$name]);
    }

    /**
     * @param $name
     * @param $method
     */
    public function register($name, $method)
    {
        $this->_method[$name] = $method;
    }

    /**
     *
     * @access public
     * @param string $method 组件名称
     * @return void
     */
    public function destory($method)
    {
        if (isset($this->_method[$method])) {
            unset($this->_method[$method]);
        }
    }
}