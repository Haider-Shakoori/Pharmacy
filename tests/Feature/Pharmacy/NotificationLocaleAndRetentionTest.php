<?php

namespace Tests\Feature\Pharmacy;

use App\Models\User;
use App\Notifications\InventoryActivityNotification;
use App\Services\Access\RbacProvisioner;
use App\Services\Alerts\InventoryActivityNotifier;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationLocaleAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_notification_history_renders_in_english_dari_and_pashto_with_correct_direction(): void
    {
        $tenant = $this->createTenant(['slug' => 'alert-locales']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@alert-locales.test', 'password123');

        $tenant->run(function () use ($owner): void {
            $owner->notify(new InventoryActivityNotification(
                'Batch status changed',
                'Batch B-10 changed from active to quarantined.',
                'batch-one',
                'warning',
                'batch_status',
                ['batch' => 'B-10', 'from' => 'active', 'to' => 'quarantined'],
            ));
        });

        $this->onTenantDomain($tenant);

        foreach ([
            'en' => ['ltr', 'My activity notifications', 'Batch status changed'],
            'fa' => ['rtl', 'تاریخچه خبرتیاهای من', 'وضعیت بچ تغییر کرد'],
            'ps' => ['rtl', 'زما د فعالیت خبرتیاوې', 'د بچ حالت بدل شو'],
        ] as $locale => [$direction, $heading, $eventTitle]) {
            $this->actingAs($owner)
                ->withSession(['locale' => $locale])
                ->get('/alerts')
                ->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('dir="'.$direction.'"', false)
                ->assertSee($heading)
                ->assertSee($eventTitle);
        }
    }

    public function test_inventory_activity_retention_removes_only_older_notifications_of_recipient(): void
    {
        $tenant = $this->createTenant(['slug' => 'alert-retention']);
        app(TrialProvisioner::class)->provision($tenant);
        $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);

        $tenant->run(function () use ($roles): void {
            $owner = User::query()->create([
                'name' => 'Owner',
                'email' => 'owner@alert-retention.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $owner->roles()->sync([$roles['owner']->id]);

            $cashier = User::query()->create([
                'name' => 'Cashier',
                'email' => 'cashier@alert-retention.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $cashier->roles()->sync([$roles['cashier']->id]);
            $cashier->notify(new InventoryActivityNotification('Keep me', 'Cashier event', 'cashier-event'));

            $rows = [];
            for ($i = 0; $i <= InventoryActivityNotifier::RETENTION_LIMIT; $i++) {
                $time = now()->subSeconds(InventoryActivityNotifier::RETENTION_LIMIT + 2 - $i)->toDateTimeString();
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => InventoryActivityNotification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $owner->id,
                    'data' => json_encode(['reference' => 'old-'.$i]),
                    'read_at' => null,
                    'created_at' => $time,
                    'updated_at' => $time,
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('notifications')->insert($chunk);
            }

            app(InventoryActivityNotifier::class)->record(
                $owner,
                new InventoryActivityNotification('New event', 'Retained', 'latest'),
            );

            $this->assertSame(InventoryActivityNotifier::RETENTION_LIMIT, $owner->notifications()->count());
            $this->assertSame(1, $cashier->notifications()->count());
            $this->assertSame('latest', $owner->notifications()->latest()->first()->data['reference']);
        });
    }
}
