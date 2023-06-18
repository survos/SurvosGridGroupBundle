<?php

declare(strict_types=1);

namespace Survos\GridGroupBundle\Model;

use App\Entity\Field\Field;
use App\Entity\Instance;

class Property implements \Stringable
{

    // since these are tied to grid-group, we can have relations and categories, though they are mostly used by Museado

    final public const TYPE_DATABASE = 'db'; // fixed properties, we need to pass it valid subtypes
    final public const TYPE_RELATION = 'rel'; // related to another grid
    final public const TYPE_CLASSIFICATION = 'cat'; // single relation within this grid, pass in valid subtypes
    final public const TYPE_ATTRIBUTE = 'att';
    final public const TYPE_REFERENCE = 'ref'; // @todo: handle images and media.  Maybe json?

    // these are really attribute types only
    final public const PROPERTY_TEXT = 'textarea';
    final public const PROPERTY_STRING = 'string';
    final public const PROPERTY_INT = 'int';
    final public const PROPERTY_NUMERIC = 'num';
    final public const PROPERTY_BOOL = 'bool';
    final public const PROPERTY_DATE = 'date';
    final public const PROPERTY_ARRAY = 'array';

    final public const ATTRIBUTE_TYPES = [self::PROPERTY_INT, self::PROPERTY_TEXT, self::PROPERTY_STRING];




    public function __construct(
        private ?string $code = null,
        private ?string $type=null, // self::PROPERTY_STRING, // rel, cat, att
        private ?string $subType = null, // e.g. type, relatedCore, dbField.  aka subType?
        private ?array $settings=[], // min/max, delimited, etc.
        private ?Schema $schema = null,

    )
    {
        if ($this->schema) {
            $schema->addProperty($this);
        }
    }

    /**
     * @return Schema
     */
    public function getSchema(): ?Schema
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
    public function getType(): ?string
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

    // this should probably be at the field, not property, level, but this way we can translate before having fields.
    public function isTranslatable(): bool
    {
        return $this->getType() == Field::TYPE_INTRINSIC && in_array($this->getSubType(), Instance::TRANSLATABLE_FIELDS);
    }

    /**
     * @return string|null
     */
    public function getSubType(): ?string
    {
        return $this->subType;
    }

    /**
     * @param string|null $subType
     * @return Property
     */
    public function setSubType(?string $subType): Property
    {
        $this->subType = $subType;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getSettings(): ?array
    {
        return $this->settings;
    }

    /**
     * @param array|null $settings
     * @return Property
     */
    public function setSettings(?array $settings): Property
    {
        $this->settings = $settings;
        return $this;
    }


    public function __toString(): string
    {
        $x = $this->getCode();
        $settings = $this->getSettings();
        if ($type = $this->getType()) {
            // if array, use the short form.
            if ($type === self::PROPERTY_ARRAY) {
                $x .= $settings['delim'];
                unset($settings['delim']);
            } else {
                // hack -- they type is wrong!
                if ($x <> $type) {
                    assert($x <> $type);
                    $x .= ':' . $type;
                }
            }
        }
        if ($subType = $this->getSubType()) {
            $x .= '.'.$subType;
        }
        if ( $settings && !empty($settings)) {
            $x .= '?' . http_build_query($settings);
        }
        return $x;

    }
}

