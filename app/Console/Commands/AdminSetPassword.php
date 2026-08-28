<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminSetPassword extends Command
{
    protected $signature = 'admin:set-password {email} {password}';

    protected $description = 'Set (or reset) the password of an existing account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found: '.$this->argument('email'));

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($this->argument('password'))])->save();

        $this->info('Password updated for '.$user->email);

        return self::SUCCESS;
    }
}
