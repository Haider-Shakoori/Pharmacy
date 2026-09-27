<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    public function test_supported_locale_is_stored_in_session(): void
    {
        $this->from('/pharmacy')
            ->get('/locale/fa')
            ->assertRedirect('/pharmacy')
            ->assertSessionHas('locale', 'fa');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->get('/locale/de')->assertNotFound();
    }
}
