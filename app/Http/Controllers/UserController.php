<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.form');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $user = new User;
            $user->name = $data['name'];
            $user->email = strtolower($data['email']);
            $user->password = $data['password'];
            $user->email_verified_at = now();
            $user->forceFill([
                'role' => UserRole::from($data['role']),
                'is_active' => $data['is_active'] ?? true,
            ]);
            $user->save();
        });

        return redirect()->route('users.index')->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return view('users.form', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $newRole = UserRole::from($data['role']);
        $actor = $request->user();

        DB::transaction(function () use ($actor, $user, $data, $newRole): void {
            $locked = $this->lockForUpdateById($user->getKey());

            $newActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $locked->isActive();

            $this->guardSelfAndLastAdmin($actor, $locked, $newRole, $newActive);

            $locked->name = $data['name'];
            $locked->email = strtolower($data['email']);

            if (! empty($data['password'])) {
                $locked->password = Hash::make($data['password']);
            }

            $locked->forceFill([
                'role' => $newRole,
                'is_active' => $newActive,
            ]);
            $locked->save();
        });

        return redirect()->route('users.index')->with('success', 'Akun berhasil diperbarui.');
    }

    /**
     * Reject changes that would lock out the acting admin or leave no active admin.
     *
     * Runs inside the update transaction. The number of other active admins is
     * derived from a locking read so concurrent demotions cannot each observe
     * a stale "one admin remains" state.
     */
    private function guardSelfAndLastAdmin(User $actor, User $target, UserRole $newRole, bool $newActive): void
    {
        $isSelf = $actor->is($target);

        if ($isSelf && (! $newActive)) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        }

        if ($isSelf && $newRole !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.',
            ]);
        }

        $removesActiveAdmin = $target->isActive()
            && $target->isAdmin()
            && (! $newActive || $newRole !== UserRole::Admin);

        if ($removesActiveAdmin && $this->activeAdminCountExcluding($target) === 0) {
            throw ValidationException::withMessages([
                'role' => 'Tidak dapat menonaktifkan atau menurunkan peran admin aktif terakhir.',
            ]);
        }
    }

    /**
     * Count active admins other than the target under a locking read.
     *
     * Every admin-management transaction reads this same set (all active
     * admin rows, ordered by primary key) with FOR UPDATE before touching the
     * target. On MySQL/InnoDB this serializes concurrent requests — two admins
     * cannot both observe "one active admin remains" and demote each other.
     * Using the identical query and ordering in every request avoids deadlocks.
     */
    private function activeAdminCountExcluding(User $target): int
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->reject(fn (User $admin): bool => $admin->is($target))
            ->count();
    }

    /**
     * Lock and reload the target row, ordered consistently with the admin-set
     * lock so every transaction acquires locks in the same sequence.
     */
    private function lockForUpdateById(int $id): User
    {
        return User::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
