<?php

declare(strict_types=1);

namespace Interfaces\Console\Identity;

use Illuminate\Support\ServiceProvider;

final class IdentityConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CreateRegularUserCommand::class, CreateAdminCommand::class]);
        }
    }
}
