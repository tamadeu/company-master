<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature = 'user:grant-admin {email}';

    protected $description = 'Grant administrator access to an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);
        $this->info("Acesso administrativo concedido para {$user->email}.");

        return self::SUCCESS;
    }
}
