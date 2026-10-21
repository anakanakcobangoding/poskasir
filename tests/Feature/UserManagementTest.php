<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests cannot access user management', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->get(route('users.create'))->assertRedirect(route('login'));
    $this->post(route('users.store'), [])->assertRedirect(route('login'));
});

test('kasir cannot view, create, or update users', function () {
    $kasir = User::factory()->kasir()->create();
    $target = User::factory()->kasir()->create();
    $this->actingAs($kasir);

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
    $this->get(route('users.edit', $target))->assertForbidden();
    $this->post(route('users.store'), [
        'name' => 'Baru',
        'email' => 'baru@example.test',
        'role' => UserRole::Kasir->value,
        'password' => 'rahasia123456',
        'password_confirmation' => 'rahasia123456',
    ])->assertForbidden();
    $this->put(route('users.update', $target), [
        'name' => 'Ubah',
        'email' => $target->email,
        'role' => UserRole::Kasir->value,
    ])->assertForbidden();
});

test('admin can view the user list', function () {
    $admin = User::factory()->admin()->create();
    $kasir = User::factory()->kasir()->create(['name' => 'Kasir Satu']);
    $this->actingAs($admin);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Kasir Satu')
        ->assertSee($kasir->email);
});

test('admin can create kasir and admin accounts', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('users.store'), [
        'name' => 'Kasir Baru',
        'email' => 'Kasir.Baru@Example.test',
        'role' => UserRole::Kasir->value,
        'password' => 'rahasia123456',
        'password_confirmation' => 'rahasia123456',
    ])->assertRedirect(route('users.index'));

    $this->post(route('users.store'), [
        'name' => 'Admin Baru',
        'email' => 'admin.baru@example.test',
        'role' => UserRole::Admin->value,
        'password' => 'rahasia123456',
        'password_confirmation' => 'rahasia123456',
    ])->assertRedirect(route('users.index'));

    $kasir = User::where('email', 'kasir.baru@example.test')->firstOrFail();
    $admin = User::where('email', 'admin.baru@example.test')->firstOrFail();

    expect($kasir->role)->toBe(UserRole::Kasir)
        ->and($kasir->isActive())->toBeTrue()
        ->and($admin->role)->toBe(UserRole::Admin);
});

test('duplicate email is rejected', function () {
    User::factory()->create(['email' => 'ada@example.test']);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('users.store'), [
        'name' => 'Duplikat',
        'email' => 'ada@example.test',
        'role' => UserRole::Kasir->value,
        'password' => 'rahasia123456',
        'password_confirmation' => 'rahasia123456',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'ada@example.test')->count())->toBe(1);
});

test('password must follow the policy on create', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('users.store'), [
        'name' => 'Lemah',
        'email' => 'lemah@example.test',
        'role' => UserRole::Kasir->value,
        'password' => 'pendek1',
        'password_confirmation' => 'pendek1',
    ])->assertSessionHasErrors('password');

    $this->post(route('users.store'), [
        'name' => 'Tanpa Angka',
        'email' => 'tanpaangka@example.test',
        'role' => UserRole::Kasir->value,
        'password' => 'hanyahurufaja',
        'password_confirmation' => 'hanyahurufaja',
    ])->assertSessionHasErrors('password');

    expect(User::where('email', 'lemah@example.test')->exists())->toBeFalse()
        ->and(User::where('email', 'tanpaangka@example.test')->exists())->toBeFalse();
});

test('empty password on edit keeps the old password', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->kasir()->create(['password' => 'password-lama']);
    $original = $target->password;

    $this->put(route('users.update', $target), [
        'name' => 'Nama Diubah',
        'email' => $target->email,
        'role' => UserRole::Kasir->value,
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->name)->toBe('Nama Diubah')
        ->and($target->password)->toBe($original);
});

test('admin can change a user role', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->kasir()->create();

    $this->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Admin->value,
    ])->assertRedirect(route('users.index'));

    expect($target->refresh()->role)->toBe(UserRole::Admin);
});

test('an inactive account cannot log in', function () {
    User::factory()->inactive()->create([
        'email' => 'nonaktif@example.test',
        'password' => 'rahasia123456',
    ]);

    $this->post(route('login.store'), [
        'email' => 'nonaktif@example.test',
        'password' => 'rahasia123456',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a deactivated account cannot keep using its old session', function () {
    $user = User::factory()->kasir()->create();
    $this->actingAs($user);

    $this->get(route('pos.index'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('pos.index'))->assertForbidden();
    $this->assertGuest();
});

test('a user can be reactivated', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->inactive()->create();

    $this->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Kasir->value,
        'is_active' => '1',
    ])->assertRedirect(route('users.index'));

    expect($target->refresh()->isActive())->toBeTrue();
});

test('the last active admin cannot be deactivated or demoted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $other = User::factory()->admin()->create();

    expect(User::where('role', UserRole::Admin)->where('is_active', true)->count())->toBe(2);

    $this->put(route('users.update', $other), [
        'name' => $other->name,
        'email' => $other->email,
        'role' => UserRole::Kasir->value,
    ])->assertRedirect(route('users.index'));

    expect($other->refresh()->role)->toBe(UserRole::Kasir)
        ->and(User::where('role', UserRole::Admin)->where('is_active', true)->count())->toBe(1);

    $lastAdmin = $admin->refresh();

    $this->put(route('users.update', $lastAdmin), [
        'name' => $lastAdmin->name,
        'email' => $lastAdmin->email,
        'role' => UserRole::Kasir->value,
    ])->assertSessionHasErrors('role');

    expect($lastAdmin->refresh()->role)->toBe(UserRole::Admin)
        ->and(User::where('role', UserRole::Admin)->where('is_active', true)->count())->toBe(1);
});

test('admin cannot deactivate or demote themselves', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->put(route('users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => UserRole::Admin->value,
        'is_active' => '0',
    ])->assertSessionHasErrors('is_active');

    $this->put(route('users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => UserRole::Kasir->value,
    ])->assertSessionHasErrors('role');

    expect($admin->refresh()->isActive())->toBeTrue()
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($other->refresh()->isActive())->toBeTrue();
});

test('role and is_active are not mass assignable', function () {
    $user = User::create([
        'name' => 'Test',
        'email' => 'mass@example.test',
        'password' => 'password',
        'role' => 'admin',
        'is_active' => false,
    ]);

    $fresh = $user->fresh();

    expect($fresh->role)->toBe(UserRole::Kasir)
        ->and($fresh->isActive())->toBeTrue();
});

test('a rejected last-admin change leaves no partial update', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $originalName = $admin->name;
    $originalEmail = $admin->email;

    $this->put(route('users.update', $admin), [
        'name' => 'Nama Baru',
        'email' => 'email.baru@example.test',
        'role' => UserRole::Kasir->value,
    ])->assertSessionHasErrors('role');

    $admin->refresh();

    expect($admin->name)->toBe($originalName)
        ->and($admin->email)->toBe($originalEmail)
        ->and($admin->role)->toBe(UserRole::Admin);
});

test('a non-admin mutation can still update profile and password', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->kasir()->create(['password' => 'password-lama']);
    $oldHash = $target->password;

    $this->put(route('users.update', $target), [
        'name' => 'Nama Baru',
        'email' => 'baru@example.test',
        'role' => UserRole::Kasir->value,
        'password' => 'sandiBaru12345',
        'password_confirmation' => 'sandiBaru12345',
    ])->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->name)->toBe('Nama Baru')
        ->and($target->email)->toBe('baru@example.test')
        ->and($target->password)->not->toBe($oldHash)
        ->and(Hash::check('sandiBaru12345', $target->password))->toBeTrue();
});
