<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StoreOperationService
{
    public const KEY = 'store_status';

    public const CLOSED_MESSAGE = 'Toko sedang tutup. Transaksi tidak dapat diproses. Silakan hubungi admin untuk membuka toko kembali.';

    public function status(): string
    {
        $setting = Setting::where('key', self::KEY)->first();

        return $setting === null || $setting->value === 'open' ? 'open' : 'closed';
    }

    /** Must be called within the transaction that writes the order. */
    public function assertOpenForTransaction(): void
    {
        $setting = $this->lockedStatus();

        if ($setting->value !== 'open') {
            throw ValidationException::withMessages(['store_status' => self::CLOSED_MESSAGE]);
        }
    }

    public function change(string $status, int $userId): void
    {
        abort_unless(in_array($status, ['open', 'closed'], true), 422);

        DB::transaction(function () use ($status, $userId): void {
            $setting = $this->lockedStatus();
            $previous = $setting->value;
            $setting->update(['value' => $status]);
            Log::info('Store operational status changed.', ['user_id' => $userId, 'previous' => $previous, 'status' => $status]);
        }, attempts: 3);
    }

    private function lockedStatus(): Setting
    {
        Setting::query()->insertOrIgnore(['key' => self::KEY, 'value' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return Setting::where('key', self::KEY)->lockForUpdate()->firstOrFail();
    }
}
