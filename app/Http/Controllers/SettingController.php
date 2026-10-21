<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateQrisImageRequest;
use App\Http\Requests\UpdateStoreProfileRequest;
use App\Models\Setting;
use App\Services\StoreOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SettingController extends Controller
{
    /**
     * Setting key that stores the path to the business QRIS image.
     */
    public const QRIS_IMAGE_KEY = 'qris_image';

    /**
     * Setting key for the store name.
     */
    public const STORE_NAME_KEY = 'store_name';

    /**
     * Setting key for the POS name.
     */
    public const POS_NAME_KEY = 'pos_name';

    /**
     * Setting key for the store status.
     */
    public const STORE_STATUS_KEY = 'store_status';

    /**
     * Setting key for the store subtitle.
     */
    public const STORE_SUBTITLE_KEY = 'subtitle';

    public const STORE_TITLE_KEY = 'main_title';

    public const STORE_LOGO_KEY = 'store_logo';

    /**
     * Disk used to store the uploaded QRIS image.
     */
    public const QRIS_IMAGE_DISK = 'public';

    /**
     * Get the current store name from settings or fallback to config.
     */
    public static function storeName(): string
    {
        return Setting::where('key', self::STORE_NAME_KEY)->value('value')
            ?? config('app.name', 'WarungCerdas');
    }

    /**
     * Get the current POS name from settings or fallback to 'POS'.
     */
    public static function posName(): string
    {
        return Setting::where('key', self::POS_NAME_KEY)->value('value')
            ?? 'POS';
    }

    /**
     * Get the current store status from settings or fallback to 'open'.
     */
    public static function storeStatus(): string
    {
        return app(StoreOperationService::class)->status();
    }

    /**
     * Check if store is open.
     */
    public static function isStoreOpen(): bool
    {
        return self::storeStatus() === 'open';
    }

    /**
     * Get the current store subtitle from settings or fallback to empty string.
     */
    public static function subtitle(): string
    {
        return Setting::where('key', self::STORE_SUBTITLE_KEY)->value('value')
            ?? '';
    }

    public static function storeTitle(): string
    {
        return Setting::where('key', self::STORE_TITLE_KEY)->value('value') ?: 'Selamat Datang di WarungCerdas POS';
    }

    public static function storeLogoUrl(): ?string
    {
        $path = Setting::where('key', self::STORE_LOGO_KEY)->value('value');
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return route('settings.store-logo', absolute: false);
    }

    public function storeLogo(): StreamedResponse
    {
        $path = Setting::where('key', self::STORE_LOGO_KEY)->value('value');
        abort_unless(is_string($path) && $path !== '' && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, headers: ['Cache-Control' => 'private, no-store']);
    }

    /**
     * Get the current QRIS image URL.
     */
    private function currentQrisImageUrl(): ?string
    {
        $path = Setting::where('key', self::QRIS_IMAGE_KEY)->value('value');

        if (! is_string($path) || $path === '') {
            return null;
        }

        $disk = Storage::disk(self::QRIS_IMAGE_DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        return route('pos.qris-image', absolute: false);
    }

    public function edit(): View
    {
        return view('settings.qris', [
            'qrisImageUrl' => $this->currentQrisImageUrl(),
            'qrisImagePath' => Setting::where('key', self::QRIS_IMAGE_KEY)->value('value'),
        ]);
    }

    public function update(UpdateQrisImageRequest $request): RedirectResponse
    {
        $oldPath = Setting::where('key', self::QRIS_IMAGE_KEY)->value('value');

        $newPath = $request->file('qris_image')->store('qris', self::QRIS_IMAGE_DISK);

        if ($newPath === false) {
            return back()->with('error', 'Gambar QRIS gagal disimpan. Coba lagi.');
        }

        try {
            DB::transaction(function () use ($newPath): void {
                Setting::updateOrCreate(
                    ['key' => self::QRIS_IMAGE_KEY],
                    ['value' => $newPath],
                );
            });
        } catch (Throwable $exception) {
            Storage::disk(self::QRIS_IMAGE_DISK)->delete($newPath);

            throw $exception;
        }

        $this->deleteIfChanged($oldPath, $newPath);

        return redirect()
            ->route('settings.qris.edit')
            ->with('success', 'Gambar QRIS berhasil diperbarui.');
    }

    public function editStoreProfile(): View
    {
        return view('settings.store-profile', [
            'storeName' => Setting::where('key', self::STORE_NAME_KEY)->value('value'),
            'posName' => Setting::where('key', self::POS_NAME_KEY)->value('value'),
            'subtitle' => Setting::where('key', self::STORE_SUBTITLE_KEY)->value('value'),
            'mainTitle' => self::storeTitle(),
            'storeLogoUrl' => self::storeLogoUrl(),
            'storeLogoPath' => Setting::where('key', self::STORE_LOGO_KEY)->value('value'),
        ]);
    }

    public function updateStoreProfile(UpdateStoreProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $newLogoPath = null;
        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->store('store-logos', 'public');
            if ($newLogoPath === false) {
                Log::error('Store logo upload could not be stored.', ['disk' => 'public']);
                throw ValidationException::withMessages(['logo' => 'Logo gagal disimpan. Periksa storage lalu coba lagi.']);
            }
        }

        DB::transaction(function () use ($validated, $newLogoPath): void {
            Setting::updateOrCreate(
                ['key' => self::STORE_NAME_KEY],
                ['value' => $validated['store_name']],
            );

            Setting::updateOrCreate(
                ['key' => self::POS_NAME_KEY],
                ['value' => $validated['pos_name']],
            );

            if (array_key_exists('main_title', $validated)) {
                Setting::updateOrCreate(['key' => self::STORE_TITLE_KEY], ['value' => $validated['main_title']]);
            }
            if ($newLogoPath !== null || ($validated['remove_logo'] ?? false)) {
                Setting::updateOrCreate(['key' => self::STORE_LOGO_KEY], ['value' => $newLogoPath]);
            }

            if (array_key_exists('subtitle', $validated)) {
                Setting::updateOrCreate(
                    ['key' => self::STORE_SUBTITLE_KEY],
                    ['value' => $validated['subtitle']],
                );
            }
        });

        return redirect()
            ->route('settings.store-profile.edit')
            ->with('success', 'Profil toko berhasil diperbarui.');
    }

    public function status(): JsonResponse
    {
        return response()->json(['status' => self::storeStatus()])->header('Cache-Control', 'no-store, private');
    }

    public function openStore(Request $request): RedirectResponse|JsonResponse
    {
        return $this->changeStoreStatus($request, 'open');
    }

    public function closeStore(Request $request): RedirectResponse|JsonResponse
    {
        return $this->changeStoreStatus($request, 'closed');
    }

    private function changeStoreStatus(Request $request, string $status): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        app(StoreOperationService::class)->change($status, (int) $request->user()->id);
        $message = $status === 'open' ? 'Toko berhasil dibuka.' : 'Toko berhasil ditutup.';

        if ($request->expectsJson()) {
            return response()->json(['status' => self::storeStatus(), 'message' => $message])->header('Cache-Control', 'no-store, private');
        }

        return back()->with('success', $message);
    }

    /**
     * Remove the previous file only after the replacement was stored and saved.
     */
    private function deleteIfChanged(?string $oldPath, string $newPath): void
    {
        if (! is_string($oldPath) || $oldPath === '' || $oldPath === $newPath) {
            return;
        }

        $disk = Storage::disk(self::QRIS_IMAGE_DISK);

        if ($disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }
    }
}
