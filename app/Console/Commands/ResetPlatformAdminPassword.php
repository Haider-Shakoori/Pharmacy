<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class ResetPlatformAdminPassword extends Command
{
    protected $signature = 'platform:admin:reset-password
        {email : Platform administrator email address}
        {--length=24 : Generated password length}';

    protected $description = 'Generate, store, verify, and print a new platform administrator password once.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $length = (int) $this->option('length');

        if ($length < 16 || $length > 64) {
            $this->error('Password length must be between 16 and 64 characters.');

            return self::FAILURE;
        }

        $admin = PlatformAdmin::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $admin) {
            $this->error('Platform administrator not found.');

            return self::FAILURE;
        }

        $password = Str::password(
            length: $length,
            letters: true,
            numbers: true,
            symbols: true,
            spaces: false,
        );

        $admin->forceFill([
            'password' => $password,
            'is_active' => true,
        ])->save();

        $admin->refresh();

        if (! Hash::check($password, $admin->password)) {
            throw new RuntimeException('The generated platform administrator password could not be verified.');
        }

        $this->warn('This password is shown once. Store it securely, then change it after signing in.');
        $this->line('Email: '.$admin->email);
        $this->line('Password: '.$password);

        return self::SUCCESS;
    }
}
