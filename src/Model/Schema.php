<?php

declare(strict_types=1);

namespace Survos\GridGroupBundle\Model;

class Schema
{

    public function __construct(
        private string $idName = 'id',
        private array $properties = []
    )
    {
    }

    static public function createFromDottedConfig(string $dottedConfig)
    {

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
        $this->properties[] = $property;
    }


}

