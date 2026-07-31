<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Thrown for Facebook/Instagram API failures that are worth retrying
 * later (rate limits, transient server errors, network blips) as
 * opposed to permanent failures (bad params, missing permissions).
 */
class TransientApiException extends RuntimeException
{
}
