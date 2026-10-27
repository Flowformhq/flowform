<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        if ($this->isDemo()) {
            $this->form->fill([
                'email' => 'admin@flowformhq.com',
                'password' => 'password',
                'remember' => true,
            ]);
        } else {
            $this->form->fill();
        }
    }

    protected function getEmailFormComponent(): Component
    {
        $component = parent::getEmailFormComponent();

        if ($this->isDemo()) {
            $component
                ->default('admin@flowformhq.com')
                ->extraInputAttributes(['value' => 'admin@flowformhq.com']);
        }

        return $component;
    }

    protected function getPasswordFormComponent(): Component
    {
        $component = parent::getPasswordFormComponent();

        if ($this->isDemo()) {
            $component
                ->default('password')
                ->extraInputAttributes(['value' => 'password']);
        }

        return $component;
    }

    protected function isDemo(): bool
    {
        $host = request()->getHost();

        return str_contains($host, 'demo')
            || request()->has('demo')
            || (bool) config('app.is_demo', false);
    }
}
