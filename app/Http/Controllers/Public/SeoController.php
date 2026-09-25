<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Site;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (Site::get('allow_indexing')) {
            // Trang sau đăng nhập, admin và API không cần index.
            foreach (['/admin', '/ajax', '/api', '/dashboard', '/new-order', '/orders', '/add-funds', '/transactions', '/tickets', '/updates', '/account'] as $path) {
                $lines[] = "Disallow: {$path}";
            }
        } else {
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('pricing'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('register'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => route('login'), 'priority' => '0.3', 'changefreq' => 'monthly'],
            ['loc' => route('terms'), 'priority' => '0.2', 'changefreq' => 'yearly'],
            ['loc' => route('privacy'), 'priority' => '0.2', 'changefreq' => 'yearly'],
        ];

        $lastmod = now()->toDateString();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc'])."</loc><lastmod>{$lastmod}</lastmod>"
                ."<changefreq>{$url['changefreq']}</changefreq><priority>{$url['priority']}</priority></url>\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml']);
    }
}
