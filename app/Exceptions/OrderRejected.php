<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An order action was refused for a reason the customer or admin can act on.
 * The message is safe to show to them as-is.
 */
class OrderRejected extends RuntimeException
{
}
