<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_locale_is_stored_in_tenant_session(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);

        $this->onTenantDomain($tenant)
            ->from('/')
            ->get('/locale/fa')
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'fa');
    }

    public function test_unsupported_locale_is_rejected_on_tenant_domain(): void
    {
        $tenant = $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);

        $this->onTenantDomain($tenant)
            ->get('/locale/de')
            ->assertNotFound();
    }

    public function test_staff_login_displays_translations_and_language_switcher_for_all_supported_locales(): void
    {
        $tenant = $this->createTenant(['name' => 'HS Pharmacy', 'slug' => 'hs-login-i18n']);
        $this->withoutVite();
        $this->onTenantDomain($tenant);

        foreach ([
            'en' => ['ltr', 'Pharmacy sign in', 'Remember me'],
            'fa' => ['rtl', 'ورود به دواخانه', 'مرا به خاطر بسپار'],
            'ps' => ['rtl', 'درملتون ته ننوتل', 'ما په یاد وساتئ'],
        ] as $locale => [$direction, $heading, $remember]) {
            $this->withSession(['locale' => $locale])
                ->get('/login')
                ->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('dir="'.$direction.'"', false)
                ->assertSee($heading)
                ->assertSee($remember)
                ->assertSee('/locale/fa', false)
                ->assertSee('/locale/ps', false)
                ->assertSee('type="submit"', false);
        }
    }


}
