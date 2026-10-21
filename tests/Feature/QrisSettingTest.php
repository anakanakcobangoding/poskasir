<?php

use App\Enums\UserRole;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('the qris settings page requires an authenticated admin', function () {
    $this->get(route('settings.qris.edit'))->assertRedirect(route('login'));

    actingAsUser(['role' => UserRole::Kasir]);

    $this->get(route('settings.qris.edit'))->assertForbidden();
});

test('an admin can view the qris settings page', function () {
    actingAsUser(['role' => UserRole::Admin]);

    $this->get(route('settings.qris.edit'))
        ->assertOk()
        ->assertSee('Gambar QRIS')
        ->assertSee('Belum ada gambar QRIS');
});

test('the qris settings page shows the current image when configured', function () {
    actingAsUser(['role' => UserRole::Admin]);

    Storage::disk('public')->put('qris/current.png', 'fake-image');
    Setting::create(['key' => 'qris_image', 'value' => 'qris/current.png']);

    $this->get(route('settings.qris.edit'))
        ->assertOk()
        ->assertSee('src="/pos/qris-image"', false)
        ->assertSee('id="qris_image"', false);
});

test('only an admin can upload a qris image', function () {
    $file = UploadedFile::fake()->image('qris.png');

    $this->put(route('settings.qris.update'), ['qris_image' => $file])
        ->assertRedirect(route('login'));

    actingAsUser(['role' => UserRole::Kasir]);

    $this->put(route('settings.qris.update'), ['qris_image' => UploadedFile::fake()->image('qris.png')])
        ->assertForbidden();

    expect(Setting::where('key', 'qris_image')->exists())->toBeFalse();
});

test('the uploaded qris image is served to a cashier without depending on the storage URL or symlink', function () {
    actingAsUser();
    config(['filesystems.disks.public.url' => 'http://localhost:8000/storage']);
    $image = UploadedFile::fake()->image('qris.png');
    $bytes = file_get_contents($image->getRealPath());
    Storage::disk('public')->put('qris/current.png', $bytes);
    Setting::create(['key' => 'qris_image', 'value' => 'qris/current.png']);

    $response = $this->get(route('pos.qris-image'))->assertOk()->assertHeader('Content-Type', 'image/png');

    expect($response->streamedContent())->toBe($bytes);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('qris image requests require login', function () {
    $this->get(route('pos.qris-image'))->assertRedirect(route('login'));
});

test('a missing qris image returns not found', function () {
    actingAsUser();
    Setting::create(['key' => 'qris_image', 'value' => 'qris/missing.png']);

    $this->get(route('pos.qris-image'))->assertNotFound();
});

test('an uploaded qris image updates the qris_image setting', function () {
    actingAsUser(['role' => UserRole::Admin]);

    $this->put(route('settings.qris.update'), [
        'qris_image' => UploadedFile::fake()->image('qris.png', 600, 800),
    ])->assertRedirect(route('settings.qris.edit'))
        ->assertSessionHas('success');

    $path = Setting::where('key', 'qris_image')->value('value');

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('a non-image upload is rejected', function () {
    actingAsUser(['role' => UserRole::Admin]);

    $this->put(route('settings.qris.update'), [
        'qris_image' => UploadedFile::fake()->create('qris.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('qris_image');

    expect(Setting::where('key', 'qris_image')->exists())->toBeFalse();
});

test('an oversized qris upload is rejected', function () {
    actingAsUser(['role' => UserRole::Admin]);

    $this->put(route('settings.qris.update'), [
        'qris_image' => UploadedFile::fake()->image('qris.png')->size(3000),
    ])->assertSessionHasErrors('qris_image');

    expect(Setting::where('key', 'qris_image')->exists())->toBeFalse();
});

test('an upload without a file is rejected', function () {
    actingAsUser(['role' => UserRole::Admin]);

    $this->put(route('settings.qris.update'), [])
        ->assertSessionHasErrors('qris_image');
});

test('replacing the qris image keeps a working reference until the new file is stored', function () {
    actingAsUser(['role' => UserRole::Admin]);

    Storage::disk('public')->put('qris/old.png', 'old-image');
    Setting::create(['key' => 'qris_image', 'value' => 'qris/old.png']);

    $this->put(route('settings.qris.update'), [
        'qris_image' => UploadedFile::fake()->image('new.png', 400, 400),
    ])->assertRedirect(route('settings.qris.edit'));

    $newPath = Setting::where('key', 'qris_image')->value('value');

    expect($newPath)->not->toBe('qris/old.png')
        ->and(Storage::disk('public')->exists($newPath))->toBeTrue()
        ->and(Storage::disk('public')->exists('qris/old.png'))->toBeFalse();
});

test('a failed replacement leaves the previous qris image untouched', function () {
    actingAsUser(['role' => UserRole::Admin]);

    Storage::disk('public')->put('qris/keep.png', 'keep-image');
    Setting::create(['key' => 'qris_image', 'value' => 'qris/keep.png']);

    $this->put(route('settings.qris.update'), [
        'qris_image' => UploadedFile::fake()->create('bad.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('qris_image');

    expect(Setting::where('key', 'qris_image')->value('value'))->toBe('qris/keep.png')
        ->and(Storage::disk('public')->exists('qris/keep.png'))->toBeTrue();
});
