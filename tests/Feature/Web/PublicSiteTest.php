<?php

namespace Tests\Feature\Web;

use App\Filament\Pages\WebsiteSettings;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_landing_page_with_seo_tags(): void
    {
        Service::factory()->create(['rate' => '0.5']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('The fastest &amp; most affordable SMM panel', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('$0.50');
    }

    public function test_logged_in_pages_are_not_indexed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_public_pricing_lists_active_services(): void
    {
        Service::factory()->create(['name' => 'Visible service']);
        Service::factory()->create(['name' => 'Hidden service', 'status' => 'disabled']);

        $this->get('/pricing')->assertOk()->assertSee('Visible service')->assertDontSee('Hidden service');
    }

    public function test_robots_and_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap: '.url('/sitemap.xml'));
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(route('pricing'));
    }

    public function test_indexing_can_be_disabled(): void
    {
        Setting::set('site.allow_indexing', false);

        $this->get('/robots.txt')->assertSee("User-agent: *\nDisallow: /", false);
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_admin_can_change_branding_and_landing_content(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(WebsiteSettings::class)
            ->fillForm([
                'name' => 'BoostVN',
                'hero_title' => 'Grow faster with BoostVN',
                'faq' => [['question' => 'Is it safe?', 'answer' => 'Yes, we never ask for your password.']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/')
            ->assertSee('<title>BoostVN – Cheapest &amp; fastest SMM panel</title>', false)
            ->assertSee('Grow faster with BoostVN')
            ->assertSee('Is it safe?')
            ->assertDontSee('What is an SMM panel?');

        $this->actingAs($admin)->get('/admin/website-settings')->assertOk();
    }
}
