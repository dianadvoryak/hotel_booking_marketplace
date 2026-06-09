<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "API системы бронирования отелей с защитой от овербукинга (Redis Lock) и фоновым экспортом данных (RabbitMQ)",
    title: "Hotel Booking Marketplace API"
)]
#[OA\Server(
    url: "/api",
    description: "Локальный сервер разработки"
)]

abstract class Controller
{
    //
}
