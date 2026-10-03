<?php

namespace App\Providers;

use App\Http\Middleware\DemoReadOnly;
use App\Http\Middleware\InjectDemoWidget;
use App\Support\Demo\DemoMode;
use App\Support\Demo\DemoModeException;
use Filament\Facades\Filament;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

use function Livewire\on;

/**
 * Chế độ demo chỉ xem (config/demo.php). Không làm gì khi DEMO_MODE=false.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! DemoMode::enabled() || ! class_exists(Filament::class)) {
            return;
        }

        // Route của panel Filament chỉ chạy middleware khai báo trong panel (không qua group "web") và được nạp
        // khi Filament boot → gắn DemoReadOnly vào từng panel trước lúc đó (booting chạy trước mọi provider boot),
        // để route POST riêng của panel (logout, export...) cũng bị chặn ngoài allowed_routes.
        $this->app->booting(function () {
            foreach (Filament::getPanels() as $panel) {
                $panel->middleware([DemoReadOnly::class]);
            }
        });
    }

    public function boot(): void
    {
        if (! DemoMode::enabled()) {
            return;
        }

        // Chặn mọi lệnh ghi SQL từ request web (kể cả query builder, không chỉ Eloquent).
        DB::beforeExecuting(fn (string $sql) => DemoMode::guardQuery($sql));

        // Không gửi email từ request web (mã 2FA, gửi thử SMTP, quên mật khẩu...), kể cả khi lệnh gửi
        // nằm sâu trong package (vd Filament) mà không gọi được DemoMode::abortIfEnabled() trước.
        Event::listen(MessageSending::class, fn () => DemoMode::abortIfEnabled());

        // Livewire / Filament: hiện thông báo trên trang thay vì trang lỗi.
        on('exception', function ($component, $e, $stopPropagation) {
            if (! $e instanceof DemoModeException) {
                return;
            }

            $component->dispatch('demo-blocked', message: $e->getMessage());
            $stopPropagation();
        });

        // Upload file (FileUpload của Filament, wire:model file): dừng ngay từ bước xin URL upload để ô upload
        // báo lỗi gọn + hiện thông báo (route livewire.upload-file vẫn bị DemoReadOnly chặn nếu gọi thẳng).
        on('call', function ($component, $method, $params, $context, $returnEarly) {
            if ($method !== '_startUpload' || ! DemoMode::guarding()) {
                return;
            }

            $component->dispatch('upload:errored', name: $params[0] ?? null)->self();
            $component->dispatch('demo-blocked', message: DemoMode::message());
            $returnEarly();
        });

        // Cột sửa trực tiếp trong bảng Filament: bấm vào sẽ bị chặn nhưng UI (Alpine) giữ trạng thái đã đổi,
        // nên khóa luôn để không hiển thị sai.
        foreach ([ToggleColumn::class, CheckboxColumn::class, SelectColumn::class, TextInputColumn::class] as $column) {
            if (class_exists($column)) {
                $column::configureUsing(fn ($column) => $column->disabled());
            }
        }

        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', DemoReadOnly::class);
        $kernel->appendMiddlewareToGroup('api', DemoReadOnly::class);
        // Middleware toàn cục (không gắn vào group web): panel Filament khai báo danh sách middleware riêng,
        // không đi qua group web, nên gắn vào group thì trang Filament không có nút Demo.
        $kernel->pushMiddleware(InjectDemoWidget::class);

        // InjectDemoWidget chạy sau khi session đã lưu (flash đã bị xoá): giữ lại thông báo "bị chặn"
        // (redirect back từ DemoModeException) lúc router dựng response, khi session vẫn còn.
        Event::listen(PreparingResponse::class, function (PreparingResponse $event) {
            if ($event->request->hasSession() && ($message = $event->request->session()->get('demo_blocked'))) {
                $event->request->attributes->set('demo_blocked', $message);
            }
        });

        $this->loadRoutesFrom(base_path('routes/demo.php'));
    }
}
