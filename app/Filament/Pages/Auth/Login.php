<?php

namespace App\Filament\Pages\Auth;

use App\Support\Demo\DemoMode;
use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Chế độ demo: điền sẵn tài khoản (?demo=<key>, mặc định tài khoản admin).
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if ($credentials = DemoMode::credentials('admin')) {
            $this->form->fill($credentials + ['remember' => true]);
        }
    }
}
