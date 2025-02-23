<?php declare(strict_types=1);

namespace Flow\Parquet\ParquetFile\Schema;

use Flow\Parquet\Consts;
use Flow\Parquet\Exception\InvalidArgumentException;
use Flow\Parquet\Thrift\SchemaElement;

/**
 * @psalm-suppress RedundantCastGivenDocblockType
 * @psalm-suppress RedundantConditionGivenDocblockType
 * @psalm-suppress DocblockTypeContradiction
 */
final class FlatColumn implements Column
{
    private ?string $flatPath = null;

    private ?NestedColumn $parent = null;

    public function __construct(
        private readonly string $name,
        private readonly PhysicalType $type,
        private readonly ?ConvertedType $convertedType = null,
        private readonly ?LogicalType $logicalType = null,
        private readonly ?Repetition $repetition = Repetition::OPTIONAL,
        private readonly ?int $precision = null,
        private readonly ?int $scale = null,
        private readonly ?int $typeLength = null,
    ) {
    }

    public static function boolean(string $name) : self
    {
        return new self($name, PhysicalType::BOOLEAN, null, null, Repetition::OPTIONAL);
    }

    public static function date(string $name) : self
    {
        return new self($name, PhysicalType::INT32, ConvertedType::DATE, LogicalType::date(), Repetition::OPTIONAL);
    }

    public static function dateTime(string $name) : self
    {
        if (PHP_INT_MAX !== Consts::PHP_INT64_MAX) {
            throw new InvalidArgumentException('PHP_INT_MAX must be equal to ' . Consts::PHP_INT64_MAX . ' to support 64-bit timestamps.');
        }

        return new self($name, PhysicalType::INT64, ConvertedType::TIMESTAMP_MICROS, LogicalType::timestamp(), Repetition::OPTIONAL);
    }

    public static function decimal(string $name, int $precision = 10, int $scale = 2) : self
    {
        if ($scale < 0 || $scale > 38) {
            throw new InvalidArgumentException('Scale must be between 0 and 38, ' . $scale . ' given.');
        }

        if ($precision < 1 || $precision > 38) {
            throw new InvalidArgumentException('Scale must be between 1 and 38, ' . $scale . ' given.');
        }

        $bitsNeeded = \ceil(\log(10 ** $precision, 2));
        $byteLength = (int) \ceil($bitsNeeded / 8);

        return new self(
            $name,
            PhysicalType::FIXED_LEN_BYTE_ARRAY,
            ConvertedType::DECIMAL,
            LogicalType::decimal($scale, $precision),
            Repetition::OPTIONAL,
            $precision,
            $scale,
            $byteLength
        );
    }

    public static function double(string $name) : self
    {
        return new self($name, PhysicalType::DOUBLE, null, null, Repetition::OPTIONAL);
    }

    public static function enum(string $string) : self
    {
        return new self($string, PhysicalType::BYTE_ARRAY, ConvertedType::ENUM, LogicalType::string(), Repetition::OPTIONAL);
    }

    public static function float(string $name) : self
    {
        return new self($name, PhysicalType::FLOAT, null, null, Repetition::OPTIONAL);
    }

    public static function fromThrift(SchemaElement $thrift) : self
    {
        return new self(
            $thrift->name,
            PhysicalType::from($thrift->type),
            $thrift->converted_type === null ? null : ConvertedType::from($thrift->converted_type),
            $thrift->logicalType === null ? null : LogicalType::fromThrift($thrift->logicalType),
            $thrift->repetition_type === null ? null : Repetition::from($thrift->repetition_type),
            $thrift->precision,
            $thrift->scale,
            $thrift->type_length,
        );
    }

    public static function int32(string $name) : self
    {
        return new self($name, PhysicalType::INT32, ConvertedType::INT_32, null, Repetition::OPTIONAL);
    }

    public static function int64(string $name) : self
    {
        if (PHP_INT_MAX !== Consts::PHP_INT64_MAX) {
            throw new InvalidArgumentException('PHP_INT_MAX must be equal to ' . Consts::PHP_INT64_MAX . ' to support 64-bit timestamps.');
        }

        return new self($name, PhysicalType::INT64, ConvertedType::INT_64, null, Repetition::OPTIONAL);
    }

    public static function json(string $string) : self
    {
        return new self($string, PhysicalType::BYTE_ARRAY, ConvertedType::JSON, LogicalType::string(), Repetition::OPTIONAL);
    }

    public static function string(string $name) : self
    {
        return new self($name, PhysicalType::BYTE_ARRAY, ConvertedType::UTF8, LogicalType::string(), Repetition::OPTIONAL);
    }

    public static function time(string $name) : self
    {
        if (PHP_INT_MAX !== Consts::PHP_INT64_MAX) {
            throw new InvalidArgumentException('PHP_INT_MAX must be equal to ' . Consts::PHP_INT64_MAX . ' to support 64-bit timestamps.');
        }

        return new self($name, PhysicalType::INT64, ConvertedType::TIME_MICROS, LogicalType::time(), Repetition::OPTIONAL);
    }

    public static function uuid(string $string) : self
    {
        return new self($string, PhysicalType::BYTE_ARRAY, null, LogicalType::string(), Repetition::OPTIONAL);
    }

    /**
     * @psalm-suppress PossiblyNullOperand
     */
    public function ddl() : array
    {
        return [
            /** @phpstan-ignore-next-line */
            'type' => $this->type()->name . ($this->logicalType()?->name() !== null ? ' (' . $this->logicalType()?->name() . ')' : ''),
            'optional' => $this->repetition()?->value === Repetition::OPTIONAL->value,
        ];
    }

    public function flatPath() : string
    {
        if ($this->flatPath !== null) {
            return $this->flatPath;
        }

        $parent = $this->parent();

        if ($parent?->schemaRoot) {
            $this->flatPath = $this->name;

            return $this->flatPath;
        }

        $path = [$this->name];

        while ($parent) {
            $path[] = $parent->name();
            $parent = $parent->parent();

            if ($parent && $parent->schemaRoot) {
                break;
            }
        }

        $path = \array_reverse($path);
        $this->flatPath = \implode('.', $path);

        return $this->flatPath;
    }

    public function isList() : bool
    {
        return false;
    }

    public function isListElement() : bool
    {
        if ($this->parent !== null) {
            // element
            if ($this->parent->logicalType()?->name() === 'LIST') {
                return true;
            }

            // list.element
            if ($this->parent->parent()?->logicalType()?->name() === 'LIST') {
                return true;
            }

            // list.element.{column}
            if ($this->parent->parent()?->parent()?->logicalType()?->name() === 'LIST') {
                return true;
            }
        }

        return false;
    }

    public function isMap() : bool
    {
        return false;
    }

    public function isMapElement() : bool
    {
        if ($this->parent === null) {
            return false;
        }

        if ($this->parent()?->logicalType()?->name() === 'MAP') {
            return true;
        }

        if ($this->parent()?->parent()?->logicalType()?->name() === 'MAP') {
            return true;
        }

        if ($this->parent()?->parent()?->parent()?->logicalType()?->name() === 'MAP') {
            return true;
        }

        return false;
    }

    public function isStruct() : bool
    {
        return false;
    }

    public function isStructElement() : bool
    {
        $parent = $this->parent();

        if ($parent === null) {
            return false;
        }

        /** @var NestedColumn $parent */
        if ($parent->isList()) {
            return false;
        }

        if ($parent->isMap()) {
            return false;
        }

        return true;
    }

    public function logicalType() : ?LogicalType
    {
        return $this->logicalType;
    }

    public function makeRequired() : self
    {
        return new self($this->name, $this->type, $this->convertedType, $this->logicalType, Repetition::REQUIRED, $this->precision, $this->scale, $this->typeLength);
    }

    public function maxDefinitionsLevel() : int
    {
        $level = $this->repetition === Repetition::REQUIRED ? 0 : 1;

        return $this->parent ? $level + $this->parent->maxDefinitionsLevel() : $level;
    }

    public function maxRepetitionsLevel() : int
    {
        $level = $this->repetition === Repetition::REPEATED ? 1 : 0;

        return $this->parent ? $level + $this->parent->maxRepetitionsLevel() : $level;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function parent() : ?NestedColumn
    {
        return $this->parent;
    }

    public function path() : array
    {
        return \explode('.', $this->flatPath());
    }

    public function precision() : ?int
    {
        return $this->precision;
    }

    public function repetition() : ?Repetition
    {
        return $this->repetition;
    }

    public function scale() : ?int
    {
        return $this->scale;
    }

    public function setParent(NestedColumn $parent) : void
    {
        $this->flatPath = null;
        $this->parent = $parent;
    }

    public function toThrift() : SchemaElement
    {
        return new SchemaElement([
            'name' => $this->name,
            'type' => $this->type->value,
            'converted_type' => $this->convertedType?->value,
            'repetition_type' => $this->repetition?->value,
            'logicalType' => $this->logicalType?->toThrift(),
            'precision' => $this->precision,
            'scale' => $this->scale,
            'type_length' => $this->typeLength,
        ]);
    }

    public function type() : PhysicalType
    {
        return $this->type;
    }

    public function typeLength() : ?int
    {
        return $this->typeLength;
    }
}
