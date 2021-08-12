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
 * @param $object
 * @param null $name
 * @param string $kat
 * @param null $function_other
 * @return string
 */
function kat_encode($object, $name = NULL, $kat = '', $function_other = NULL)
{
    switch (gettype($object)) {
        case 'NULL':
            $kat .= 'n';
            if ($name != NULL) {
                $kat .= ":$name";
            }
            $kat .= '()';
            break;
        case 'string':
            $kat .= 's';
            if ($name != NULL) {
                $kat .= ":$name";
            }
            $kat .= '(' . preg_replace('([()^])', "^$0", $object) . ')';
            break;
        case 'boolean':
            $kat .= 'b';
            if ($name != NULL) {
                $kat .= ":$name";
            }
            $kat .= '(' . ($object ? '1' : '0') . ')';
            break;
        case 'integer':
            $kat .= $object > 2147483647 ? 'l' : 'i';
            if ($name != NULL) {
                $kat .= ":$name";
            }
            $kat .= '(' . $object . ')';
            break;
        case 'double':
            $larger = $object > 2147483647;
            settype($object, 'string');
            $kat .= strpos($object, '.') ? ($larger ? 'd' : 'f') : ($larger ? 'l' : 'i');
            if ($name != NULL) {
                $kat .= ":$name";
            }
            $kat .= '(' . $object . ')';
            unset($larger);
            break;
        case 'object':
            switch (get_class($object)) {
                case 'Exception':
                    $kat .= 'E';
                    if ($name != NULL) {
                        $kat .= ":$name";
                    }
                    $kat .= '{i:code(' . $object->getCode() . ')s:msg(' . $object->getMessage() . ')}';
                    break;
                case 'DateTime':
                    $kat .= 'D';
                    if ($name != NULL) {
                        $kat .= ":$name";
                    }
                    $kat .= '(' . $object->format(DateTime::ISO8601) . ')';
                    break;
                default:
                    if (is_callable($function_other)) {
                        $kat .= call_user_func_array($function_other, [$object, $name, '', $function_other]);
                    }
            }
            break;
        case 'array':
            $pos = 0;
            $struct = false;
            foreach ($object as $key => $value) {
                if ($pos !== $key) {
                    $struct = true;
                    break;
                }
                $pos++;
            }

            if ($struct) {
                $kat .= 'M';
                if ($name != NULL) {
                    $kat .= ":$name";
                }
                $kat .= '{';
                foreach ($object as $name => $value) {
                    $kat .= kat_encode($value, $name, '', $function_other);
                }
                $kat .= '}';
            } else {
                $kat .= 'A';
                if ($name != NULL) {
                    $kat .= ":$name";
                }
                $kat .= '{';
                foreach ($object as $name => $value) {
                    $kat .= kat_encode($value, NULL, '', $function_other);
                }
                $kat .= '}';
            }
            unset($pos, $struct);
            break;
        default:
            $kat .= 'N';
            if ($name != NULL) {
                $kat .= ":$name";
            }
            $kat .= '[' . $object . ']';
    }
    return $kat;
}

/**
 * @param $kat
 * @return mixed
 */
function kat_decode($kat)
{
    switch ($kat[0]) {
        case '[':
        case '{':
            return json_decode($kat, true);
    }
    $parser = Kat_Parser::get();
    $parser->read($kat);

    return $parser->export();
}

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
 * Kat_Parser
 *
 * @package Kat
 */
class Kat_Parser
{
    /**
     * @var Kat_Parser
     */
    protected static $parser;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var String
     */
    protected $label;

    /**
     * @var array
     */
    protected $classes, $spaces, $aliases;

    /**
     * @var array
     */
    protected $stack, $data;

    /**
     * @var array
     */
    protected $space, $alias, $symbol;

    /**
     * STATUS
     */
    const SPACE = 0, ALIAS = 1, SYMBOL = 2;

    /**
     * Kat_Reader
     *
     */
    public function __construct()
    {
        if (!function_exists('string_pop')) {
            /**
             * @param $s
             * @return string
             */
            function string_pop(&$s)
            {
                $str = '';
                foreach ($s as $ch) {
                    $str .= chr($ch);
                }
                $s = [];
                return $str;
            }

        }
    }

    /**
     * @return Kat_Parser
     */
    public static function get()
    {
        return Kat_Parser::$parser == NULL ? Kat_Parser::$parser = new Kat_Parser() : Kat_Parser::$parser;
    }

    /**
     * @return String
     */
    public function label()
    {
        return $this->label;
    }

    /**
     * @return mixed
     */
    public function export()
    {
        return array_pop($this->data);
    }

    /**
     * @param $data
     */
    public function read($data)
    {
        $this->startDoc();

        // trim
        $data = trim($data);

        // ergodic
        for ($pos = 0, $length = strlen($data); $pos < $length; $pos++) {
            $ch = ord($data[$pos]);
            // status
            switch ($this->status) {
                case Kat_Parser::SYMBOL:
                {
                    switch ($ch) {
                        case 41:
                        {
                            $this->enterStack(array_pop($this->aliases), $this->parseSymbol(
                                array_pop($this->spaces), string_pop($this->symbol)
                            ));
                            $this->status = Kat_Parser::SPACE;
                            break;
                        }
                        case 94:
                        {
                            $c = ord($data[++$pos]);
                            switch ($c) {
                                case 40:
                                case 41:
                                case 94:
                                {
                                    $this->symbol[] = $c;
                                }
                            }
                            break;
                        }
                        default:
                        {
                            $this->symbol[] = $ch;
                        }
                    }
                    break;
                }
                case Kat_Parser::SPACE:
                {
                    switch ($ch) {
                        case 123:
                        {
                            $this->status = Kat_Parser::SPACE;
                            $this->startSpace(
                                string_pop($this->space),
                                string_pop($this->alias)
                            );
                            break;
                        }
                        case 40:
                        {
                            $this->status = Kat_Parser::SYMBOL;
                            $this->spaces[] = string_pop($this->space);
                            $this->aliases[] = string_pop($this->alias);
                            break;
                        }
                        case 125:
                        {
                            $this->endSpace(
                                array_pop($this->spaces),
                                array_pop($this->aliases)
                            );
                            $this->status = Kat_Parser::SPACE;
                            break;
                        }
                        case 58:
                        {
                            $this->status = Kat_Parser::ALIAS;
                            break;
                        }
                        case 32:
                        case 9:
                        case 10:
                        case 13:
                        {
                            break;
                        }
                        default:
                        {
                            $this->space[] = $ch;
                        }
                    }
                    break;
                }
                case Kat_Parser::ALIAS:
                {
                    switch ($ch) {
                        case 123:
                        {
                            $this->status = Kat_Parser::SPACE;
                            $this->startSpace(
                                string_pop($this->space),
                                string_pop($this->alias)
                            );
                            break;
                        }
                        case 40:
                        {
                            $this->status = Kat_Parser::SYMBOL;
                            $this->spaces[] = string_pop($this->space);
                            $this->aliases[] = string_pop($this->alias);
                            break;
                        }
                        case 94:
                        {
                            $pos++;
                            break;
                        }
                        default:
                        {
                            $this->alias[] = $ch;
                        }
                    }
                }
            }
        }

        $this->endDoc();
    }

    /**
     * start document
     */
    protected function startDoc()
    {
        $this->classes = [];
        $this->stack = [];
        $this->spaces = [];
        $this->aliases = [];
        $this->data = [];

        $this->space = [];
        $this->alias = [];
        $this->symbol = [];

        $this->status = Kat_Parser::SPACE;
    }

    /**
     * end document
     */
    protected function endDoc()
    {

    }

    /**
     * @param $clazz
     * @param $alias
     */
    protected function startSpace($clazz, $alias)
    {
        $this->spaces[] = $clazz;
        $this->aliases[] = $alias;
        $this->classes[] = $clazz;
        switch ($clazz) {
            case 'L':
            case 'A':
            case 'M':
            {
                $this->stack[] = [];
                break;
            }
        }
    }

    /**
     * @param $clazz
     * @param $alias
     */
    protected function endSpace($clazz, $alias)
    {
        array_pop($this->classes);
        switch ($clazz) {
            case 'M':
            case 'A':
            case 'L':
            {
                $this->enterStack($alias, array_pop($this->stack));
            }
        }
    }

    /**
     * @param $clazz
     * @param $val
     * @return mixed
     */
    protected function parseSymbol($clazz, $val)
    {
        switch ($clazz) {
            case 'N':
            case 's':
                return $val;
            case 'b':
                return (boolean)$val;
            case 'i':
            case 'l':
                return (int)$val;
            case 'f':
                return (float)$val;
            case 'd':
                return (double)$val;
            case 'n':
                return null;
            case 'D':
                try {
                    return new DateTime($val);
                } catch (Exception $e) {
                    return new DateTime();
                }
            case 'B':
                return base64_decode($val);
        }
        return $val;
    }

    /**
     * @param $alias
     * @param $value
     */
    protected function enterStack($alias, $value)
    {
        if (count($this->classes)) {
            $index = count($this->stack) - 1;
            switch (end($this->classes)) {
                case 'M':
                {
                    $this->stack[$index][$alias] = $value;
                    break;
                }
                case 'A':
                {
                    $this->stack[$index][] = $value;
                }
            }
            unset($index);
        } else {
            $this->label = $alias;
            $this->data[] = $value;
        }
    }
}