<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation would leave the installation with no super_admin.
 *
 * The panel would keep working until the last session expired, and then
 * nobody could administer anything — including granting the role back.
 */
class LastSuperAdminException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This is the last super admin. Grant the role to another user before removing it from this one.');
    }
}
