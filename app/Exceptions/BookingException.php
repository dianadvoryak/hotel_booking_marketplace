<?php

namespace App\Exceptions;

use Exception;

class BookingException extends Exception
{
    public static function noRoomsAvailable(): self
    {
        return new self('К сожалению, на выбранные даты нет свободных номеров.', 422);
    }

    public static function dynamicLockFailed(): self
    {
        return new self('Система обрабатывает другой запрос на этот номер. Попробуйте еще раз через секунду.', 423);
    }
}
