<?php declare(strict_types=1);

namespace Flow\ETL\PHP\Type\Logical;

use Flow\ETL\PHP\Type\Logical\Map\MapKey;
use Flow\ETL\PHP\Type\Logical\Map\MapValue;
use Flow\ETL\PHP\Type\Type;
use Flow\Serializer\Serializable;

/**
 * @implements Serializable<array{key: MapKey, value: MapValue}>
 */
final class MapType implements LogicalType, Serializable
{
    public function __construct(private readonly Map\MapKey $key, private readonly Map\MapValue $value)
    {
    }

    public function __serialize() : array
    {
        return ['key' => $this->key, 'value' => $this->value];
    }

    public function __unserialize(array $data) : void
    {
        $this->key = $data['key'];
        $this->value = $data['value'];
    }

    public function isEqual(Type $type) : bool
    {
        if (!$type instanceof self) {
            return false;
        }

        return $this->key->toString() === $type->key()->toString() && $this->value->toString() === $type->value()->toString();
    }

    public function isValid(mixed $value) : bool
    {
        if (!\is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (!$this->key->isValid($key)) {
                return false;
            }

            if (!$this->value->isValid($item)) {
                return false;
            }
        }

        return true;
    }

    public function key() : MapKey
    {
        return $this->key;
    }

    public function toString() : string
    {
        return 'map<' . $this->key->toString() . ', ' . $this->value->toString() . '>';
    }

    public function value() : MapValue
    {
        return $this->value;
    }
}
