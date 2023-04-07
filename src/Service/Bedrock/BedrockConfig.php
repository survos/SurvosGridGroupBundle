<?php

namespace Survos\GridGroupBundle\Service\Bedrock;

use Flintstone\Config;
use Flintstone\Database;
use SplFileObject;
use SplTempFileObject;

class BedrockConfig extends Config
{

    private bool $hash = false;
    private string $keyName;
    private array $fields = [];
    private array $headers = [];

    public function __construct(protected $config)
    {
        $headers = $this->config['headers']??[];
        if (count($headers) == 0) {
            throw new \LogicException("Headers must contain at least one field");
        }
        if (!array_key_exists('keyName', $this->config)) {
            throw new \LogicException("keyName is a required field");
        }
        $this->keyName = $this->config['keyName'];
        parent::__construct($this->config); // extra fields are ignored
//        if (empty($this->keyName)) {
//            throw new \LogicException("You must set the key name");
////            $this->keyName = $headers[0];
//        }
        if (!array_key_exists($this->keyName, $headers)) {
            array_unshift($headers, $this->keyName);
        }
        $this->setHeaders($headers);
        $this->setHash($this->config['hashKey']??false);

    }

    /**
     * @return bool
     */
    public function isHash(): bool
    {
        return $this->hash;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param bool $hash
     */
    public function setHash(bool $hash): void
    {
        $this->hash = $hash;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }





}
