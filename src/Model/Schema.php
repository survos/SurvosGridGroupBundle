<?php

declare(strict_types=1);

namespace Survos\GridGroupBundle\Model;

class Schema
{

    public function __construct(
        private string $idName = 'id',
        private array $properties = [],
        private array $valueRules = []
    )
    {
    }

    /**
     * @return array
     */
    public function getValueRules(): array
    {
        return $this->valueRules;
    }

    /**
     * @param array $valueRules
     * @return Schema
     */
    public function setValueRules(array $valueRules): Schema
    {
        $this->valueRules = $valueRules;
        return $this;
    }

    /**
     * @return string
     */
    public function getIdName(): string
    {
        return $this->idName;
    }

    /**
     * @param string $idName
     * @return Schema
     */
    public function setIdName(string $idName): Schema
    {
        $this->idName = $idName;
        return $this;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getPropertyCodes(): array
    {
        return array_keys($this->getProperties());
    }

    public function getPropertyCount(): int
    {
        return count($this->getProperties());
    }

    /**
     * @param array $properties
     * @return Schema
     */
    public function setProperties(array $properties): Schema
    {
        $this->properties = $properties;
        return $this;
    }

    public function addProperty(Property $property) {
        $this->properties[$property->getCode()] = $property;
    }

    public function getProperty(string $code): ?Property
    {
        return $this->properties[$code]??null;
    }


}

