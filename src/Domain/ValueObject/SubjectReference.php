<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\ValueObject;

use Fedale\AuthorizationTokenBundle\Domain\Exception\AuthorizationTokenException;

/**
 * A pointer to any application domain object, by type and identifier.
 *
 * The bundle never loads the referenced entity; it only stores and compares
 * the reference. The "type" is an opaque string (often a FQCN, but not
 * required to be) and the "id" is its string identifier.
 */
final class SubjectReference
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
    ) {
        if ($type === '' || $id === '') {
            throw new AuthorizationTokenException('SubjectReference requires a non-empty type and id.');
        }
    }

    /**
     * Parse the canonical "type#id" form produced by toString().
     */
    public static function fromString(string $value): self
    {
        $position = strpos($value, '#');

        if ($position === false) {
            throw new AuthorizationTokenException(sprintf('Invalid SubjectReference "%s", expected "type#id".', $value));
        }

        return new self(substr($value, 0, $position), substr($value, $position + 1));
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    public function __toString(): string
    {
        return $this->type.'#'.$this->id;
    }
}
