<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A brand logo could not be written to disk (the folder cannot be created, or the web server's user
 * may not write there). The admin controller turns it into a plain message instead of a 500.
 */
class LogoNotSaved extends RuntimeException
{
}
