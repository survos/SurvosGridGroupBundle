<?php

namespace Survos\GridGroupBundle\Service\Bedrock;

class BedrockRow
{
    public function __construct(private array $data, private string $key)
    {
        if (!array_key_exists($this->key, $this->data)) {
            throw new \LogicException(sprintf("Key %s is missing, rebuild the index?", $this->key));
        }
    }

    public function getKeyValue(): string
    {
        return $this->data[$this->key];
    }

    public function getData(): array
    {
        return $this->data;
    }

}
