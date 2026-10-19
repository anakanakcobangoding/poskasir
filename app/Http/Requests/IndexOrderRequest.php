<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop unrecognized filter values before validation so an invalid query
     * string falls back to the default listing instead of redirecting (and
     * potentially looping) on a GET request.
     */
    protected function prepareForValidation(): void
    {
        $status = $this->query('status');

        if ($status !== null && $status !== 'all' && OrderStatus::tryFrom((string) $status) === null) {
            $this->merge(['status' => null]);
        }

        $type = $this->query('type');

        if ($type !== null && OrderType::tryFrom((string) $type) === null) {
            $this->merge(['type' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['all', ...array_column(OrderStatus::cases(), 'value')])],
            'type' => ['nullable', Rule::enum(OrderType::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Filter status tidak dikenali.',
            'type.enum' => 'Filter tipe pesanan tidak dikenali.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'status',
            'type' => 'tipe pesanan',
        ];
    }
}
