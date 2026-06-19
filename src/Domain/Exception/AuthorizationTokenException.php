<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Domain\Exception;

/**
 * Base type for every exception raised by the bundle.
 *
 * Catch this to handle any token failure generically, or one of the
 * subclasses to react to a specific failure mode.
 */
class AuthorizationTokenException extends \RuntimeException
{
}
