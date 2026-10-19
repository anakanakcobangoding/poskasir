<?php

namespace App\Http\Requests;

use App\Enums\OrderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OrderType::class)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'table_number' => ['nullable', 'string', 'max:50', 'required_if:type,DI'],
            'delivery_address' => ['nullable', 'string', 'max:500', 'required_if:type,DLV'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.topping_ids' => ['nullable', 'array'],
            'items.*.topping_ids.*' => ['integer', 'distinct', 'exists:toppings,id'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
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
            'type.required' => 'Tipe pesanan wajib dipilih.',
            'type.enum' => 'Tipe pesanan tidak dikenali.',
            'customer_name.max' => 'Nama pelanggan maksimal 255 karakter.',
            'table_number.required_if' => 'Nomor meja wajib diisi untuk pesanan dine-in.',
            'table_number.max' => 'Nomor meja maksimal 50 karakter.',
            'delivery_address.required_if' => 'Alamat pengiriman wajib diisi untuk pesanan delivery.',
            'delivery_address.max' => 'Alamat pengiriman maksimal 500 karakter.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
            'items.required' => 'Keranjang masih kosong, tambahkan minimal satu produk.',
            'items.array' => 'Daftar item pesanan tidak valid.',
            'items.min' => 'Keranjang masih kosong, tambahkan minimal satu produk.',
            'items.*.product_id.required' => 'Produk pada salah satu item tidak lengkap.',
            'items.*.product_id.integer' => 'Produk pada salah satu item tidak valid.',
            'items.*.product_id.exists' => 'Ada produk yang tidak ditemukan. Muat ulang halaman kasir lalu coba lagi.',
            'items.*.quantity.required' => 'Jumlah pesanan wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah pesanan harus berupa angka.',
            'items.*.quantity.min' => 'Jumlah pesanan minimal 1.',
            'items.*.quantity.max' => 'Jumlah pesanan maksimal 99.',
            'items.*.topping_ids.array' => 'Daftar topping tidak valid.',
            'items.*.topping_ids.*.integer' => 'Topping pada salah satu item tidak valid.',
            'items.*.topping_ids.*.distinct' => 'Topping pada satu item tidak boleh dipilih dua kali.',
            'items.*.topping_ids.*.exists' => 'Ada topping yang tidak ditemukan. Muat ulang halaman kasir lalu coba lagi.',
            'items.*.notes.max' => 'Catatan item maksimal 255 karakter.',
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
            'type' => 'tipe pesanan',
            'customer_name' => 'nama pelanggan',
            'table_number' => 'nomor meja',
            'delivery_address' => 'alamat pengiriman',
            'notes' => 'catatan',
            'items' => 'item pesanan',
        ];
    }
}
