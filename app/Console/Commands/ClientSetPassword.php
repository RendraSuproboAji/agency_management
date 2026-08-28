<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ClientSetPassword extends Command
{
    protected $signature = 'client:set-password {slug} {password} {--enable : Sekalian aktifkan akses portal}';

    protected $description = 'Set the portal password of a client';

    public function handle(): int
    {
        $client = Client::where('slug', $this->argument('slug'))->first();

        if (! $client) {
            $this->error('Client not found: '.$this->argument('slug'));

            return self::FAILURE;
        }

        $client->forceFill(array_filter([
            'password' => Hash::make($this->argument('password')),
            'portal_enabled' => $this->option('enable') ?: null,
        ], fn ($value) => $value !== null))->save();

        $this->info('Portal password updated for '.$client->name);

        return self::SUCCESS;
    }
}
