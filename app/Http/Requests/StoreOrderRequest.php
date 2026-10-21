<?php

namespace App\Http\Requests;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
        $rules = [
            'type' => ['required', Rule::enum(OrderType::class)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'table_number' => ['nullable', 'string', 'max:50', 'required_if:type,DI'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_platform' => ['nullable', 'string', 'max:50', Rule::in(['shopeefood', 'gofood', 'grabfood']), 'required_if:type,DLV'],
            'notes' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage']), Rule::requiredIf(fn (): bool => is_numeric($this->input('discount_value')) && (float) $this->input('discount_value') > 0)],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2', 'required_with:discount_type', Rule::when($this->input('discount_type') === 'percentage', ['max:100'])],
            'additional_fee_label' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn (): bool => is_numeric($this->input('additional_fee_amount')) && (float) $this->input('additional_fee_amount') > 0)],
            'additional_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class), 'required_if:payment_status,paid'],
            'payment_confirmed' => ['nullable', 'boolean', 'required_if:payment_status,paid', 'accepted_if:payment_status,paid'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash', 'prohibited_if:payment_method,qris'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.toppings' => ['nullable', 'array'],
            'items.*.toppings.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.topping_ids' => ['nullable', 'array'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];

        $items = $this->input('items', []);

        if (is_array($items)) {
            foreach (array_keys($items) as $index) {
                $rules["items.{$index}.toppings.*.id"] = ['required', 'integer', 'distinct', 'exists:toppings,id'];
                $rules["items.{$index}.topping_ids.*"] = ['integer', 'distinct', 'exists:toppings,id'];
            }
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discount_type.in' => 'Pilih diskon nominal atau persentase.',
            'discount_value.min' => 'Diskon tidak boleh negatif.',
            'discount_value.max' => 'Diskon melebihi batas yang diperbolehkan.',
            'additional_fee_label.required' => 'Label biaya tambahan wajib diisi.',
            'additional_fee_amount.min' => 'Biaya tambahan tidak boleh negatif.',
            'additional_fee_amount.numeric' => 'Nominal biaya tambahan harus berupa angka.',
            'type.required' => 'Tipe pesanan wajib dipilih.',
            'type.enum' => 'Tipe pesanan tidak dikenali.',
            'customer_name.max' => 'Nama pelanggan maksimal 255 karakter.',
            'table_number.required_if' => 'Nomor meja wajib diisi untuk pesanan dine-in.',
            'table_number.max' => 'Nomor meja maksimal 50 karakter.',
            'delivery_address.max' => 'Alamat pengiriman maksimal 500 karakter.',
            'delivery_platform.required_if' => 'Pilih platform delivery (ShopeeFood, GoFood, atau GrabFood).',
            'delivery_platform.in' => 'Platform delivery tidak dikenali.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
            'payment_status.enum' => 'Status pembayaran tidak dikenali.',
            'payment_method.enum' => 'Metode pembayaran tidak dikenali.',
            'payment_method.required_if' => 'Metode pembayaran wajib dipilih untuk pesanan yang sudah dibayar.',
            'payment_confirmed.required_if' => 'Konfirmasi pembayaran wajib dicentang sebelum menandai pesanan lunas.',
            'payment_confirmed.accepted_if' => 'Konfirmasi pembayaran harus dicentang untuk pesanan yang sudah dibayar.',
            'payment_confirmed.boolean' => 'Konfirmasi pembayaran tidak valid.',
            'amount_paid.numeric' => 'Jumlah uang diterima harus berupa angka.',
            'amount_paid.min' => 'Jumlah uang diterima tidak boleh negatif.',
            'amount_paid.required_if' => 'Jumlah uang diterima wajib diisi untuk pembayaran tunai.',
            'amount_paid.prohibited_if' => 'Pembayaran QRIS tidak menerima input jumlah uang diterima.',
            'items.required' => 'Keranjang masih kosong, tambahkan minimal satu produk.',
            'items.array' => 'Daftar item pesanan tidak valid.',
            'items.min' => 'Keranjang masih kosong, tambahkan minimal satu produk.',
            'items.max' => 'Jumlah baris item maksimal 50 per pesanan.',
            'items.*.product_id.required' => 'Produk pada salah satu item tidak lengkap.',
            'items.*.product_id.integer' => 'Produk pada salah satu item tidak valid.',
            'items.*.product_id.exists' => 'Ada produk yang tidak ditemukan. Muat ulang halaman kasir lalu coba lagi.',
            'items.*.quantity.required' => 'Jumlah pesanan wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah pesanan harus berupa angka.',
            'items.*.quantity.min' => 'Jumlah pesanan minimal 1.',
            'items.*.quantity.max' => 'Jumlah pesanan maksimal 99.',
            'items.*.toppings.array' => 'Daftar topping tidak valid.',
            'items.*.toppings.*.id.required' => 'Topping pada salah satu item tidak lengkap.',
            'items.*.toppings.*.id.integer' => 'Topping pada salah satu item tidak valid.',
            'items.*.toppings.*.id.distinct' => 'Topping pada satu item tidak boleh dipilih dua kali.',
            'items.*.toppings.*.id.exists' => 'Ada topping yang tidak ditemukan. Muat ulang halaman kasir lalu coba lagi.',
            'items.*.toppings.*.quantity.required' => 'Jumlah topping wajib diisi.',
            'items.*.toppings.*.quantity.integer' => 'Jumlah topping harus berupa angka.',
            'items.*.toppings.*.quantity.min' => 'Jumlah topping minimal 1.',
            'items.*.toppings.*.quantity.max' => 'Jumlah topping maksimal 20.',
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
            'delivery_platform' => 'platform delivery',
            'notes' => 'catatan',
            'items' => 'item pesanan',
            'payment_status' => 'status pembayaran',
            'payment_method' => 'metode pembayaran',
            'payment_confirmed' => 'konfirmasi pembayaran',
            'amount_paid' => 'jumlah uang diterima',
        ];
    }
}
