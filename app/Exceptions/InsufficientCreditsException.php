<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised inside the booking transaction when the conditional debit affects
 * no rows — i.e. the balance fell below the price between the pre-flight
 * check and the debit itself. Rolls the booking back.
 */
class InsufficientCreditsException extends RuntimeException {}
