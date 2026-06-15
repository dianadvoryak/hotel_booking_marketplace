<?php

namespace Tests\Unit;

use App\Http\Requests\ConfirmBookingRequest;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ConfirmBookingRequestTest extends TestCase
{
    /**
     * Тест: Валидация падает, если передан некорректный токен (не UUID).
     */
    public function test_it_fails_if_reservation_token_is_not_valid_uuid(): void
    {
        $request = new ConfirmBookingRequest();

        // Передаем заведомо некорректную строку вместо UUID
        $data = [
            'reservation_token' => 'not-a-valid-uuid-string'
        ];

        $validator = Validator::make($data, $request->rules());

        // Утверждаем, что валидация не прошла
        $this->assertFalse($validator->passes());

        // Проверяем, что ошибка зафиксирована именно на поле reservation_token
        $this->assertArrayHasKey('reservation_token', $validator->errors()->messages());
    }

    /**
     * Тест: Валидация успешно проходит, если передан корректный UUID.
     */
    public function test_it_passes_if_reservation_token_is_correct_uuid(): void
    {
        $request = new ConfirmBookingRequest();

        // Генерируем валидный UUID "на лету"
        $data = [
            'reservation_token' => Str::uuid()->toString()
        ];

        $validator = Validator::make($data, $request->rules());

        // Утверждаем, что валидация успешно сдана
        $this->assertTrue($validator->passes());
    }
}
