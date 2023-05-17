<?php

declare(strict_types=1);

namespace Survos\GridGroupBundle\Model;

class Property
{

    final public const PROPERTY_TEXT = 'textarea';
    final public const PROPERTY_STRING = 'string';
    final public const PROPERTY_INT = 'integer';

    final public const PROPERTY_NUMERIC = 'int';

    final public const PROPERTY_BOOL = 'bool';

    final public const PROPERTY_DATE = 'date';


    public function __construct(
        private Schema $schema,
        private string $code,
        private string $type=self::PROPERTY_STRING

    )
    {
        $schema->addProperty($this);
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    /**
     * @param Schema $schema
     * @return Property
     */
    public function setSchema(Schema $schema): Property
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return Property
     */
    public function setCode(string $code): Property
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return Property
     */
    public function setType(string $type): Property
    {
        $this->type = $type;
        return $this;
    }

    public function isId(): bool
    {
        return $this->code == $this->schema->getIdName();
    }


}

