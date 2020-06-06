<?php

namespace Ebcms\SimpleCache\Exception;

use Psr\SimpleCache\InvalidArgumentException as PsrCacheInvalidArgumentException;

class InvalidArgumentException extends \InvalidArgumentException implements PsrCacheInvalidArgumentException
{
}
