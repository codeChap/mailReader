<?php

namespace Codechap\MailReader\Traits;

use InvalidArgumentException;

/**
 * GetSet Trait
 *
 * Provides dynamic property access with validation for classes.
 */
trait GetSet
{
    /**
     * Set a property value
     *
     * @param string $property The property name
     * @param mixed $value The value to set
     * @return $this For method chaining
     * @throws InvalidArgumentException If property doesn't exist
     */
    public function set(string $property, mixed $value): self
    {
        if (!property_exists($this, $property)) {
            throw new InvalidArgumentException("Property '$property' does not exist");
        }

        $this->$property = $value;
        return $this;
    }

    /**
     * Get a property value
     *
     * @param string $property The property name
     * @return mixed The property value
     * @throws InvalidArgumentException If property doesn't exist
     */
    public function get(string $property): mixed
    {
        if (!property_exists($this, $property)) {
            throw new InvalidArgumentException("Property '$property' does not exist");
        }

        return $this->$property;
    }
}