<?php

namespace Survos\GridGroupBundle\CsvSchema;

use Illuminate\Support\Collection;
use League\Csv\Reader;
use Survos\GridGroupBundle\CsvSchema\Exceptions\CastException;
use Survos\GridGroupBundle\CsvSchema\Exceptions\UnsupportedTypeException;
/**
 * CSV Parser class. This is where the magic happens.
 *
 * @author K.Sassnowski <ksassnowski@gmail.com>
 *
 * from https://github.com/shakahl/csv-schema
 */
class Parser
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $defaultDelimiter = ',';

    /**
     * @var string
     */
    private $defaultEnclosure = '"';

    /**
     * @var string
     */
    private $defaultEscape = '\\';

    /**
     * @var string
     */
    private $defaultEncoding = 'UTF-8';
    /**
     * @var bool
     */
    private $skipTitle = false;

    /**
     * @var array
     */
    private static $customTypes = [];

    /**
     * Parser constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Register a handler for a custom type. The handler will be called with
     * the value to parse.
     *
     * @param string   $type
     * @param callable $callback
     */
    public static function registerType($type, callable $callback)
    {
        static::$customTypes[$type] = $callback;
    }

    // https://stackoverflow.com/questions/22539633/parse-string-containing-dots-in-php
    // since parse_str can't handle .

    static public function parseQueryString($data): array
    {
        $data = preg_replace_callback('/(?:^|(?<=&))[^=[]+/', function ($match) {
            return bin2hex(urldecode($match[0]));
        }, $data);

        parse_str($data, $values);

        return array_combine(array_map('hex2bin', array_keys($values)), $values);
    }


    /**
     * @param $input
     *
     * @return array
     */
    public function fromString($input)
    {
        return $this->parse(Reader::createFromString($input));
    }

    public function fromFile(string $filename): \Generator
    {
        return $this->parse(Reader::createFromPath($filename));
    }

    /**
     * @param array $columns
     *
     * @return array
     */
    public function parseRow(array $columns)
    {
        $schema = $this->config['schema'];
        if (count($schema) <> count($columns)) {
            dd('columns mismatch', $schema, $columns);
        }
        assert(count($schema) == count($columns), "mismatch %d %d", );

        $zipper = collect($columns)->zip($schema);
        $valueRules = $this->config['valueRules']??[];
        $flat = $zipper->flatMap(function ($pair, $index) use ($valueRules, $schema) {
            list($value, $type) = $pair;
            foreach ($valueRules as $valueRule => $newValue) {
                if ($value == $valueRule) {
                    $value = $newValue;
                }
            }

            $key = array_keys($schema)[$index];
            $parsed = $this->getValue($type, $value, $key);
            return [$key => $parsed];
        });
        $all = $flat->all();
        return $all;
    }

    /**
     * @param Reader $reader
     *
     * @return array
     */
    protected function parse(Reader $reader): \Generator
    {
        $reader->setDelimiter($this->getConfigValue('delimiter', $this->defaultDelimiter));
        $reader->setEnclosure($this->getConfigValue('enclosure', $this->defaultEnclosure));
        $reader->setEscape($this->getConfigValue('escape', $this->defaultEscape));
        // All conversion methods are removed in favor of conversion classes, use League\Csv\CharsetConverter
//        $reader->setInputEncoding($this->getConfigValue('encoding', $this->defaultEncoding));

//        $rows =  new Collection($reader);
        foreach ($reader->getIterator() as $idx=>$row) {
//        foreach ($rows as $idx => $row) {
            if ($idx == 0 && ($this->getConfigValue('skipTitle', $this->skipTitle))) {
                continue;
            }

//            dd($this->config['schema']);
            $parsedRow = $this->parseRow($row);
            yield $parsedRow;
        }

    }

    /**
     * @param string $key
     * @param string $default
     *
     * @return mixed
     */
    protected function getConfigValue($key, $default)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * @param string $type
     * @param string $value
     *
     * @return mixed
     *
     * @throws UnsupportedTypeException
     */

    protected function getValue($config, $value, $key)
    {
        // format: [fieldname:][dottedConfig]?settings
        if (!str_contains($config, '?')) {
            $config .= '?';
        }
        [$dottedConfig, $settingsString] = explode('?', $config);
        if (str_contains($dottedConfig, ':')) {
            [$header, $dottedConfig] = explode(':', $dottedConfig);
        } else {
            $header = $key;
        }

        $settings = self::parseQueryString($settingsString);
//        if (count($settings)) {
//            dd($settings);
//        }
        if (str_contains($dottedConfig, '.')) {
            [$type, $values] = explode('.', $dottedConfig, 2);
            $parameters = $values; // what's after the .
        } else {
            $type = $dottedConfig; // no params, native type, like string, which is really att.string?
            $parameters = null;
        }
//        $values = explode('.', $dottedConfig);
//        $type = array_shift($values);
        if ($type == '') {
            $type = 'string';
        }
//        dd($header, $config, $value, $key, $settings, $values, $type);
        assert($type);

//        list($type, $parameters) = $this->parseType($type);
//        dd($type, $values, $parameters);
        $methodName = $this->getMethodName($type);
        if (method_exists($this, $methodName)) {
            $method = [$this, $this->getMethodName($type)];
        } elseif ($this->hasCustomType($type)) {
            $method = static::$customTypes[$type];
        } else {
            throw new UnsupportedTypeException($type);
        }
        return call_user_func_array($method, [$value, $parameters, $settings]);
    }

    /**
     * @param string $type
     *
     * @return array
     */
    protected function parseType($type)
    {
        $parameters = [];

        if (strpos($type, ':') !== false) {
            list($type, $parameters) = explode(':', $type, 2);
        }

        return [$type, $parameters];
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getMethodName($type)
    {
        return 'parse'.ucfirst($type);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function parseString($value)
    {
        return (string) $value;
    }

    /**
     * @param string $value
     *
     * @return int
     *
     * @throws CastException
     */
    protected function parseInt($value)
    {
        $this->guardAgainstNonNumeric($value, 'int');

        return (int) $value;
    }

    protected function parseBool(string|int $value): bool
    {
        return (bool) $value;
    }


    /**
     * @param string $value
     *
     * @return float
     *
     * @throws CastException
     */
    protected function parseFloat($value)
    {
        $this->guardAgainstNonNumeric($value, 'float');

        return (float) $value;
    }

    /**
     * @param string $string
     * @param string $delimiter
     *
     * @return array
     */
    protected function parseArray($string, $delimiter)
    {
        return explode($delimiter, trim($string));
    }

    /**
     * @param string $value
     * @param string $targetType
     *
     * @throws CastException
     */
    protected function guardAgainstNonNumeric($value, $targetType)
    {
        if (!is_numeric($value) && $value != '') {
            assert(false, "Unable to cast value '$value' to type $targetType.");
            throw new CastException("Unable to cast value '$value' to type $targetType.");
        }
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    protected function hasCustomType($type)
    {
        return isset(static::$customTypes[$type]);
    }
}
