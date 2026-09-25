<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Site;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Trang Terms / Privacy – nội dung Markdown do admin sửa ở "Website settings → Legal pages". */
class LegalController extends Controller
{
    public function terms(): View
    {
        return $this->render('Terms of Service', 'terms_content');
    }

    public function privacy(): View
    {
        return $this->render('Privacy Policy', 'privacy_content');
    }

    private function render(string $title, string $key): View
    {
        $markdown = strtr((string) Site::get($key), [
            '{site_name}' => Site::name(),
            '{support_email}' => Site::get('support_email') ?: 'our support team',
        ]);

        return view('legal', [
            'title' => $title,
            // Không cho HTML thô trong nội dung admin nhập → tránh chèn script.
            'html' => Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
        ]);
    }
}
