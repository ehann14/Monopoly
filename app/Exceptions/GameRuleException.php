<?php

namespace App\Exceptions;

use RuntimeException;

/** Dilempar saat aksi melanggar aturan game. Pesannya aman ditampilkan ke pemain. */
class GameRuleException extends RuntimeException
{
}