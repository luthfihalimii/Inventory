<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'admin_korlab']));

        return $user;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function itemPayload(array $overrides = []): array
    {
        return [
            'code' => 'ITM-9001',
            'name' => 'Kamera Test',
            'category_id' => Category::factory()->create()->id,
            'location_id' => Location::factory()->create()->id,
            'tracking_method' => 'quantity',
            'quantity_total' => 5,
            'status' => 'active',
            ...$overrides,
        ];
    }

    public function test_only_admin_korlab_can_manage_inventory(): void
    {
        $this->actingAs($this->userWithRole('user'))
            ->get(route('admin.inventory.items.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('wm'))
            ->get(route('admin.inventory.items.index'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.items.index'))
            ->assertOk();
    }

    public function test_everyone_can_browse_the_catalog(): void
    {
        $item = Item::factory()->quantity()->create(['name' => 'Kabel HDMI']);

        $this->actingAs($this->userWithRole('user'))
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/Index')
                ->has('items.data', 1)
                ->where('items.data.0.code', $item->code));

        $this->actingAs($this->userWithRole('user'))
            ->get(route('inventory.show', $item))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/Show')
                ->where('item.id', $item->id)
                ->where('canManage', false));
    }

    public function test_admin_can_create_an_item_with_a_qr_token(): void
    {
        $payload = $this->itemPayload();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.store'), $payload)
            ->assertRedirect();

        $item = Item::where('code', 'ITM-9001')->firstOrFail();

        $this->assertSame(5, $item->quantity_total);
        $this->assertSame(40, strlen($item->qr_token));
        $this->assertSame(1, AuditLog::where('action', 'item.created')->count());
    }

    public function test_item_code_must_be_unique(): void
    {
        $existing = Item::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.store'), $this->itemPayload(['code' => $existing->code]))
            ->assertSessionHasErrors('code');
    }

    public function test_admin_can_create_a_serialized_item_and_its_units(): void
    {
        $admin = $this->admin();
        $item = Item::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.inventory.assets.store'), [
                'item_id' => $item->id,
                'asset_code' => 'AST-9001',
                'serial_number' => 'SN-9001',
                'condition' => 'good',
                'status' => 'available',
            ])
            ->assertRedirect();

        $asset = Asset::where('asset_code', 'AST-9001')->firstOrFail();

        $this->assertSame($item->id, $asset->item_id);
        $this->assertSame(40, strlen($asset->qr_token));
        $this->assertSame(1, AuditLog::where('action', 'asset.created')->count());
    }

    public function test_units_cannot_be_added_to_quantity_tracked_items(): void
    {
        $item = Item::factory()->quantity()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.assets.store'), [
                'item_id' => $item->id,
                'asset_code' => 'AST-9002',
                'condition' => 'good',
                'status' => 'available',
            ])
            ->assertSessionHasErrors('item_id');
    }

    public function test_quantity_cannot_drop_below_open_reports(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        DamageReport::factory()->create(['item_id' => $item->id, 'quantity' => 4]);

        $this->actingAs($this->admin())
            ->put(route('admin.inventory.items.update', $item), $this->itemPayload([
                'code' => $item->code,
                'name' => $item->name,
                'category_id' => $item->category_id,
                'location_id' => $item->location_id,
                'quantity_total' => 3,
            ]))
            ->assertSessionHasErrors('quantity_total');

        $this->assertSame(5, $item->fresh()->quantity_total);
    }

    public function test_damaged_serialized_unit_is_not_available_until_repaired(): void
    {
        $admin = $this->admin();
        $item = Item::factory()->create();
        $asset = Asset::factory()->for($item)->create(['status' => 'available']);

        $this->actingAs($admin)
            ->post(route('admin.inventory.damage-reports.store', $item), [
                'asset_id' => $asset->id,
                'type' => 'damage',
                'description' => 'Lensa lecet.',
            ])
            ->assertRedirect();

        $report = DamageReport::firstOrFail();
        $asset->refresh();

        $this->assertSame('damaged', $asset->status);
        $this->assertSame('open', $report->status);
        $this->assertSame(0, app(AvailabilityService::class)->summary($item->fresh())['available']);

        $this->actingAs($admin)
            ->put(route('admin.inventory.damage-reports.resolve', $report), ['resolution' => 'repaired'])
            ->assertRedirect();

        $this->assertSame('available', $asset->fresh()->status);
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame(1, app(AvailabilityService::class)->summary($item->fresh())['available']);
        $this->assertSame(1, AuditLog::where('action', 'damage.reported')->count());
        $this->assertSame(1, AuditLog::where('action', 'damage.resolved')->count());
    }

    public function test_written_off_quantity_reduces_stock(): void
    {
        $admin = $this->admin();
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);

        $this->actingAs($admin)
            ->post(route('admin.inventory.damage-reports.store', $item), [
                'type' => 'loss',
                'quantity' => 2,
                'description' => 'Hilang saat kegiatan.',
            ])
            ->assertRedirect();

        $report = DamageReport::firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.inventory.damage-reports.resolve', $report), ['resolution' => 'written_off'])
            ->assertRedirect();

        $this->assertSame(3, $item->fresh()->quantity_total);
        $this->assertSame(3, app(AvailabilityService::class)->summary($item->fresh())['available']);
    }

    public function test_damage_quantity_cannot_exceed_available_stock(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.damage-reports.store', $item), [
                'type' => 'damage',
                'quantity' => 3,
                'description' => 'Rusak.',
            ])
            ->assertSessionHasErrors('quantity');
    }

    public function test_non_admin_cannot_report_damage(): void
    {
        $item = Item::factory()->create();

        $this->actingAs($this->userWithRole('user'))
            ->post(route('admin.inventory.damage-reports.store', $item), [
                'type' => 'damage',
                'description' => 'Rusak.',
            ])
            ->assertForbidden();
    }

    public function test_qr_page_is_printable(): void
    {
        $item = Item::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.items.qr', $item))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/inventory/items/Qr')
                ->where('items.0.qr_token', $item->qr_token));
    }

    public function test_admin_can_print_multiple_qr_labels(): void
    {
        $first = Item::factory()->create(['name' => 'AAA Kamera']);
        $second = Item::factory()->quantity()->create(['name' => 'ZZZ Tripod']);
        Asset::factory()->for($first)->create(['asset_code' => 'UNIT-1']);

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.items.qr.batch', [
                'ids' => [$first->id, $second->id],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/inventory/items/Qr')
                ->has('items', 2)
                ->has('items.0.assets', 1));

        $this->actingAs($this->userWithRole('user'))
            ->get(route('admin.inventory.items.qr.batch', ['ids' => [$first->id]]))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_write_inventory(): void
    {
        $item = Item::factory()->create();
        $asset = Asset::factory()->for($item)->create();

        $user = $this->userWithRole('user');
        $payload = $this->itemPayload();

        $this->actingAs($user)
            ->post(route('admin.inventory.items.store'), $payload)
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.inventory.items.update', $item), [...$payload, 'code' => $item->code])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.inventory.categories.store'), ['name' => 'X'])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.inventory.assets.update', $asset), [
                'asset_code' => $asset->asset_code,
                'condition' => 'good',
                'status' => 'available',
            ])
            ->assertForbidden();
    }

    public function test_qr_token_cannot_be_set_from_the_payload(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.store'), [
                ...$this->itemPayload(),
                'qr_token' => 'attacker-controlled',
            ])
            ->assertRedirect();

        $item = Item::where('code', 'ITM-9001')->firstOrFail();

        $this->assertNotSame('attacker-controlled', $item->qr_token);
        $this->assertSame(40, strlen($item->qr_token));
    }

    public function test_tracking_method_cannot_change_once_units_exist(): void
    {
        $item = Item::factory()->create();
        Asset::factory()->for($item)->create();

        $this->actingAs($this->admin())
            ->put(route('admin.inventory.items.update', $item), [
                'code' => $item->code,
                'name' => $item->name,
                'category_id' => $item->category_id,
                'location_id' => $item->location_id,
                'tracking_method' => 'quantity',
                'quantity_total' => 1,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('tracking_method');

        $this->assertSame('serialized', $item->fresh()->tracking_method);
    }

    public function test_report_rejects_an_asset_from_another_item(): void
    {
        $item = Item::factory()->create();
        $other = Item::factory()->create();
        $asset = Asset::factory()->for($other)->create(['status' => 'available']);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.damage-reports.store', $item), [
                'asset_id' => $asset->id,
                'type' => 'damage',
                'description' => 'Rusak.',
            ])
            ->assertSessionHasErrors('asset_id');

        $this->assertSame('available', $asset->fresh()->status);
    }

    public function test_asset_status_cannot_be_restored_while_a_report_is_open(): void
    {
        $item = Item::factory()->create();
        $asset = Asset::factory()->for($item)->create(['status' => 'damaged']);
        DamageReport::factory()->forAsset($asset)->create();

        $this->actingAs($this->admin())
            ->put(route('admin.inventory.assets.update', $asset), [
                'asset_code' => $asset->asset_code,
                'condition' => 'good',
                'status' => 'available',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('damaged', $asset->fresh()->status);
    }

    public function test_written_off_serialized_asset_becomes_lost(): void
    {
        $item = Item::factory()->create();
        $asset = Asset::factory()->for($item)->create(['status' => 'available']);
        $report = DamageReport::factory()->forAsset($asset)->create(['type' => 'loss']);

        $this->actingAs($this->admin())
            ->put(route('admin.inventory.damage-reports.resolve', $report), ['resolution' => 'written_off'])
            ->assertRedirect();

        $this->assertSame('lost', $asset->fresh()->status);
        $this->assertSame('resolved', $report->fresh()->status);
    }

    public function test_resolve_rejects_an_already_resolved_report(): void
    {
        $report = DamageReport::factory()->resolved()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.inventory.damage-reports.resolve', $report), ['resolution' => 'repaired'])
            ->assertSessionHasErrors('resolution');
    }

    public function test_resolve_saves_maintenance_cost_and_vendor(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);
        $report = DamageReport::factory()->create(['item_id' => $item->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.inventory.damage-reports.resolve', $report), [
                'resolution' => 'repaired',
                'resolution_notes' => 'Ganti lensa.',
                'cost' => 150000,
                'vendor' => 'CV Optik Jaya',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame('150000.00', $report->cost);
        $this->assertSame('CV Optik Jaya', $report->vendor);
    }

    public function test_catalog_schedule_lists_reservations_and_open_loans(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);

        Reservation::factory()->create([
            'item_id' => $item->id,
            'status' => 'active',
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(5),
            'quantity' => 2,
        ]);

        $loan = LoanTransaction::factory()->create([
            'status' => 'borrowed',
            'checked_out_at' => now()->subDay(),
            'due_at' => now()->addDays(10),
        ]);

        LoanItem::factory()->create([
            'loan_transaction_id' => $loan->id,
            'item_id' => $item->id,
            'asset_id' => null,
            'quantity' => 1,
        ]);

        $this->actingAs($this->userWithRole('user'))
            ->get(route('inventory.show', $item))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/Show')
                ->has('schedule', 2)
                ->where('schedule.0.kind', 'reservation')
                ->where('schedule.0.quantity', 2)
                ->where('schedule.1.kind', 'loan')
                ->where('schedule.1.quantity', 1));
    }

    public function test_maintenance_history_is_visible_to_admins_only(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);

        DamageReport::factory()->create([
            'item_id' => $item->id,
            'description' => 'Tombol rusak.',
            'cost' => 75000,
            'vendor' => 'Bengkel Elektronik',
            'status' => 'resolved',
            'resolution' => 'repaired',
            'resolved_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('inventory.show', $item))
            ->assertInertia(fn ($page) => $page
                ->has('maintenanceHistory', 1)
                ->where('maintenanceHistory.0.description', 'Tombol rusak.')
                ->where('maintenanceHistory.0.vendor', 'Bengkel Elektronik'));

        $this->actingAs($this->userWithRole('user'))
            ->get(route('inventory.show', $item))
            ->assertInertia(fn ($page) => $page->has('maintenanceHistory', 0));
    }
}
