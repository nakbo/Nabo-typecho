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
 * @param $any
 * @param null $name
 * @return string
 */
function kat_encode($any, $name = null)
{
    // encode
    $katy = new Katy();
    $katy->encode(
        $any, $name
    );

    // collect
    $kat = $katy->toString();
    unset($katy);

    return $kat;
}

/**
 * @param $kat
 * @return mixed
 */
function kat_decode($kat)
{
    // borrow
    $parser = Kat_Parser::borrow();

    // parser
    $parser->read($kat);
    $callback = $parser->export();

    // retreat
    Kat_Parser::retreat($parser);

    return $callback;
}

/**
 * Katy
 *
 * @package Kat
 */
class Katy
{
    /**
     * @var string
     */
    protected $kat = '';

    /**
     * @param $data
     * @return $this
     */
    public function add($data)
    {
        $this->kat .= $data;
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function pack($data)
    {
        $this->kat .= '(';
        $this->kat .= $data;
        $this->kat .= ')';
        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function alias($name)
    {
        if ($name !== null) {
            $this->kat .= ':';
            for ($i = 0, $l = strlen($name); $i < $l; $i++) {
                switch ($name[$i]) {
                    case '^':
                    case ':':
                    case '(':
                    case ')':
                    case '{':
                    case '}':
                    case '<':
                    case '>':
                        $this->kat .= '^';
                }
                $this->kat .= $name[$i];
            }
        }
        return $this;
    }

    /**
     * @param $val
     * @return $this
     */
    public function value($val)
    {
        $this->kat .= '(';
        for ($i = 0, $l = strlen($val); $i < $l; $i++) {
            switch ($val[$i]) {
                case '^':
                case '(':
                case ')':
                    $this->kat .= '^';
            }
            $this->kat .= $val[$i];
        }
        $this->kat .= ')';
        return $this;
    }

    /**
     * @param $any
     * @param null $name
     */
    public function encode($any, $name = null)
    {
        switch (gettype($any)) {
            case 'NULL':
                $this->add('n')->alias($name)->add('()');
                break;
            case 'string':
                $this->add('s')->alias($name)->value($any);
                break;
            case 'boolean':
                $this->add('b')->alias($name)->pack(
                    $any ? '1' : '0'
                );
                break;
            case 'integer':
                $this->add(
                    $any > 2147483647 ? 'l' : 'i'
                )->alias($name)->pack($any);
                break;
            case 'double':
                $larger = $any > 2147483647;
                settype($any, 'string');
                $this->add(strpos($any, '.') ?
                    ($larger ? 'd' : 'f') : ($larger ? 'l' : 'i')
                )->alias($name)->pack($any);
                unset($larger);
                break;
            case 'object':
                $this->dispose($any, $name);
                break;
            case 'array':
                $i = 0;
                $mut = false;
                foreach ($any as $key => $value) {
                    if ($i !== $key) {
                        $mut = true;
                        break;
                    }
                    $i++;
                }

                if ($mut) {
                    $this->add('M')->alias($name);
                    $this->add('{');
                    foreach ($any as $key => $value) {
                        $this->encode(
                            $value, $key
                        );
                    }
                    $this->add('}');
                } else {
                    $this->add('A')->alias($name);
                    $this->add('{');
                    foreach ($any as $key => $value) {
                        $this->encode(
                            $value
                        );
                    }
                    $this->add('}');
                }
                unset($i, $mut);
        }
    }

    /**
     * @param $any
     * @param null $name
     */
    public function dispose($any, $name = null)
    {
        switch (get_class($any)) {
            case 'KatAry':
            {
                $this->add(
                    $any->space() ?: 'L'
                )->alias($name);
                $this->add('{');
                foreach ($any->entry() as $key => $value) {
                    $this->encode($value);
                }
                $this->add('}');
                break;
            }
            case 'KatAny':
            {
                $this->add(
                    $any->space() ?: 'M'
                )->alias($name);
                $this->add('{');
                foreach ($any->entry() as $key => $value) {
                    $this->encode(
                        $value, $key
                    );
                }
                $this->add('}');
                break;
            }
            case 'Crash':
            case 'Exception':
                $this->add('E')->alias($name);
                $this->add('{i:c(')->add(
                    $any->getCode()
                )->add(')s:m(')->add(
                    $any->getMessage()
                )->add(')}');
                break;
            case 'DateTime':
                $this->add('D')->alias($name)->pack(
                    $any->format(DateTime::ISO8601)
                );
                break;
        }
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->kat;
    }
}

/**
 * Kat_Parser
 *
 * @package Kat
 */
class Kat_Parser
{
    /**
     * @var array
     */
    static $POOL = [];

    /**
     * @var int
     */
    protected $event;

    /**
     * @var String
     */
    protected $alia;

    /**
     * @var array
     */
    protected $clazzs, $spaces, $aliaes;

    /**
     * @var array
     */
    protected $data, $relay;

    /**
     * @var array
     */
    protected $space, $alias, $value;

    /**
     * STATUS
     */
    const SPACE = 0, ALIAS = 1, VALUE = 2;

    /**
     * Kat_Parser
     */
    public function __construct()
    {
        $this->reuse();
    }

    /**
     * @return Kat_Parser
     */
    public static function borrow()
    {
        if (empty($parser = array_pop(self::$POOL))) {
            return new Kat_Parser();
        }
        return $parser;
    }

    /**
     * @param Kat_Parser $parser
     */
    public static function retreat($parser)
    {
        if (isset($parser) && count(self::$POOL) < 32) {
            $parser->reuse();
            self::$POOL[] = $parser;
        } else {
            unset($parser);
        }
    }

    /**
     * @return String
     */
    public function label()
    {
        return $this->alia;
    }

    /**
     * @return mixed
     */
    public function export()
    {
        return array_pop(
            $this->data
        );
    }

    /**
     * @target reuse
     */
    public function reuse()
    {
        // clear
        $this->space = [];
        $this->alias = [];
        $this->value = [];

        $this->data = [];
        $this->relay = [];

        $this->clazzs = [];
        $this->spaces = [];
        $this->aliaes = [];

        // event
        $this->alia = '';
        $this->event = Kat_Parser::SPACE;
    }

    /**
     * @param $data
     */
    public function read($data)
    {
        // trim
        $data = trim($data);

        // count
        $i = 0;
        $k = strlen($data);

        // ergodic
        while ($i < $k) {
            $b = $data[$i++];
            // event
            switch ($this->event) {
                case Kat_Parser::VALUE:
                {
                    switch ($b) {
                        case ')':
                        {
                            $this->dispose();
                            $this->event = Kat_Parser::SPACE;
                            break;
                        }
                        case '^':
                        {
                            $c = $data[$i++];
                            switch ($c) {
                                case '^':
                                case '(':
                                case ')':
                                {
                                    $this->value[] = $c;
                                }
                            }
                            break;
                        }
                        default:
                        {
                            $this->value[] = $b;
                        }
                    }
                    break;
                }
                case Kat_Parser::SPACE:
                {
                    switch ($b) {
                        case '{':
                        {
                            $this->event = Kat_Parser::SPACE;
                            $this->creating();
                            break;
                        }
                        case '(':
                        {
                            $this->event = Kat_Parser::VALUE;
                            $this->analyze();
                            break;
                        }
                        case '}':
                        {
                            $this->packing();
                            $this->event = Kat_Parser::SPACE;
                            break;
                        }
                        case ':':
                        {
                            $this->event = Kat_Parser::ALIAS;
                            break;
                        }
                        case '':
                        case "\r":
                        case "\n":
                        case "\t":
                        {
                            break;
                        }
                        default:
                        {
                            $this->space[] = $b;
                        }
                    }
                    break;
                }
                case Kat_Parser::ALIAS:
                {
                    switch ($b) {
                        case '{':
                        {
                            $this->event = Kat_Parser::SPACE;
                            $this->creating();
                            break;
                        }
                        case '(':
                        {
                            $this->event = Kat_Parser::VALUE;
                            $this->analyze();
                            break;
                        }
                        case '^':
                        {
                            $c = $data[$i++];
                            switch ($c) {
                                case '^':
                                case ':':
                                case '(':
                                case ')':
                                case '{':
                                case '}':
                                case '<':
                                case '>':
                                {
                                    $this->value[] = $c;
                                }
                            }
                            break;
                        }
                        default:
                        {
                            $this->alias[] = $b;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $data
     * @return string
     */
    protected function collect(&$data)
    {
        $string = join(
            '', $data
        );
        $data = [];
        return $string;
    }

    /**
     * @target creating
     */
    protected function creating()
    {
        $clazz = $this->collect($this->space);
        $alias = $this->collect($this->alias);

        $this->spaces[] = $clazz;
        $this->aliaes[] = $alias;
        $this->clazzs[] = $clazz;

        switch ($clazz) {
            case 'L':
            case 'A':
            case 'M':
            {
                $this->relay[] = [];
                break;
            }
        }
    }

    /**
     * @target packing
     */
    protected function packing()
    {
        $clazz = array_pop($this->spaces);
        $alias = array_pop($this->aliaes);

        array_pop($this->clazzs);
        switch ($clazz) {
            case 'M':
            case 'A':
            case 'L':
            {
                $this->builder($alias,
                    array_pop($this->relay)
                );
            }
        }
    }

    /**
     * @target analyze
     */
    protected function analyze()
    {
        $this->spaces[] = $this->collect($this->space);
        $this->aliaes[] = $this->collect($this->alias);
    }

    /**
     * @target dispose
     */
    protected function dispose()
    {
        $this->builder(
            array_pop($this->aliaes),
            $this->casting(
                array_pop($this->spaces),
                $this->collect($this->value)
            )
        );
    }

    /**
     * @param $alias
     * @param $value
     */
    protected function builder($alias, $value)
    {
        if (empty($this->clazzs)) {
            $this->alia = $alias;
            $this->data[] = $value;
        } else {
            $index = count($this->relay) - 1;
            switch (end($this->clazzs)) {
                case 'M':
                {
                    $this->relay[$index][$alias] = $value;
                    break;
                }
                case 'A':
                {
                    $this->relay[$index][] = $value;
                }
            }
            unset($index);
        }
    }

    /**
     * @param $clazz
     * @param $val
     * @return mixed
     */
    protected function casting($clazz, $val)
    {
        switch ($clazz) {
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
}