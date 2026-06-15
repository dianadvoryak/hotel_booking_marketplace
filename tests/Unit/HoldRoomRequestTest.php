<?php

namespace Tests\Unit;

use App\Http\Requests\HoldRoomRequest;
use Tests\TestCase; // Используем базовый класс тестов Laravel для работы с Validator
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HoldRoomRequestTest extends TestCase
{
    /**
     * Тест: Проверяем, что валидация дат успешно ФЕЙЛИТСЯ, если выезд раньше заезда.
     */
    public function test_it_fails_if_checkout_is_before_checkin(): void
    {
        $request = new HoldRoomRequest();

        $checkIn = Carbon::now()->addMonths(2)->toDateString();
        $checkOut = Carbon::now()->addMonths(2)->subDay()->toDateString();

        $data = [
            'room_type_id' => '9c3a4f61-2e61-4b13-bb14-9b5cfda18712',
            'check_in'     => $checkIn,
            'check_out'    => $checkOut,
        ];

        // Убираем правило 'exists', чтобы тест не лез в базу данных
        $rules = $request->rules();
        $rules['room_type_id'] = ['required', 'uuid'];

        $validator = Validator::make($data, $rules);

        // Утверждаем, что валидация НЕ прошла (вернула false)
        $this->assertFalse($validator->passes());

        // Утверждаем, что ошибка возникла именно в поле check_out
        $this->assertArrayHasKey('check_out', $validator->errors()->messages());
    }

    /**
     * Тест: Проверяем, что валидация дат успешно ПРОХОДИТ с корректными датами.
     */
    public function test_it_passes_with_valid_future_dates(): void
    {
        $request = new HoldRoomRequest();

        $checkIn = Carbon::now()->addMonths(3)->toDateString();
        $checkOut = Carbon::now()->addMonths(3)->addDays(5)->toDateString();

        $data = [
            'room_type_id' => '9c3a4f61-2e61-4b13-bb14-9b5cfda18712',
            'check_in'     => $checkIn,
            'check_out'    => $checkOut,
        ];

        $rules = $request->rules();
        $rules['room_type_id'] = ['required', 'uuid'];

        $validator = Validator::make($data, $rules);

        // Утверждаем, что валидация успешно прошла (вернула true)
        $this->assertTrue($validator->passes());
    }
}
