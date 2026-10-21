<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin 
                            {--promote= : Promote an existing user by email to admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the first administrator account securely (interactive).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('promote')) {
            $email = strtolower(trim((string) $this->option('promote')));

            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->error('Pengguna tidak ditemukan.');

                return self::FAILURE;
            }

            if ($user->role === UserRole::Admin) {
                $this->info('Pengguna sudah berperan sebagai admin.');

                return self::SUCCESS;
            }

            $user->forceFill(['role' => UserRole::Admin])->save();
            $this->info('Pengguna berhasil dipromosikan menjadi admin.');

            return self::SUCCESS;
        }

        $existingAdmin = User::where('role', UserRole::Admin)->exists();

        if ($existingAdmin) {
            $this->warn('Sudah terdapat akun admin. Gunakan --promote=email untuk mempromosikan akun lain jika diperlukan.');

            $proceed = $this->confirm('Tetap lanjut membuat akun admin baru?', false);

            if (! $proceed) {
                return self::SUCCESS;
            }
        }

        $name = text(
            label: 'Nama lengkap',
            required: true,
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Format email tidak valid.',
                User::where('email', strtolower(trim($value)))->exists() => 'Email sudah digunakan.',
                default => null,
            },
        );

        $email = strtolower(trim($email));

        $pass = password(
            label: 'Kata sandi (minimal 12 karakter)',
            required: true,
        );

        $passConfirm = password(
            label: 'Konfirmasi kata sandi',
            required: true,
        );

        if ($pass !== $passConfirm) {
            $this->error('Konfirmasi kata sandi tidak cocok.');

            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $pass], [
            'password' => ['required', Password::min(12)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = trim($name);
        $user->email = $email;
        $user->password = Hash::make($pass);
        $user->email_verified_at = now();
        $user->forceFill(['role' => UserRole::Admin]);
        $user->save();

        $this->info('Akun admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
