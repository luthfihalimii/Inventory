<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'code,name,category,location,tracking_method,quantity_total,price,acquired_at,status,notes,asset_codes';

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin_korlab']));

        return $user;
    }

    private function csv(string ...$rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'items.csv',
            self::HEADER."\n".implode("\n", $rows)."\n",
        );
    }

    public function test_admin_can_import_items_and_their_units(): void
    {
        Category::factory()->create(['name' => 'Kamera']);
        Location::factory()->create(['name' => 'Lab Jurnalistik', 'code' => 'LAB']);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.import'), [
                'file' => $this->csv(
                    'CAM-1,Kamera A,Kamera,Lab Jurnalistik,quantity,3,1000000,2024-01-15,active,,',
                    'TRI-1,Tripod A,Kamera,LAB,serialized,,,2024-02-01,active,,TRI-A;TRI-B',
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $camera = Item::where('code', 'CAM-1')->firstOrFail();
        $this->assertSame(3, $camera->quantity_total);
        $this->assertSame('2024-01-15', $camera->acquired_at->toDateString());
        $this->assertNotNull($camera->qr_token);

        $tripod = Item::where('code', 'TRI-1')->firstOrFail();
        $this->assertNull($tripod->quantity_total);
        $this->assertSame(
            ['TRI-A', 'TRI-B'],
            $tripod->assets()->orderBy('asset_code')->pluck('asset_code')->all(),
        );
        $this->assertSame(2, Asset::count());
        $this->assertSame(2, AuditLog::where('action', 'item.created')->count());
    }

    public function test_one_bad_row_cancels_the_whole_import(): void
    {
        Category::factory()->create(['name' => 'Kamera']);
        Location::factory()->create(['name' => 'Lab Jurnalistik']);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.import'), [
                'file' => $this->csv(
                    'CAM-1,Kamera A,Kamera,Lab Jurnalistik,quantity,3,,,active,,',
                    'CAM-2,Kamera B,Tidak Ada,Lab Jurnalistik,quantity,1,,,active,,',
                ),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Item::count());
        $this->assertSame(0, Asset::count());
    }

    public function test_existing_codes_and_units_are_rejected(): void
    {
        Category::factory()->create(['name' => 'Kamera']);
        Location::factory()->create(['name' => 'Lab Jurnalistik']);
        Item::factory()->create(['code' => 'CAM-1']);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.import'), [
                'file' => $this->csv('CAM-1,Kamera A,Kamera,Lab Jurnalistik,quantity,3,,,active,,'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, Item::count());
    }

    public function test_duplicate_rows_inside_the_file_are_rejected(): void
    {
        Category::factory()->create(['name' => 'Kamera']);
        Location::factory()->create(['name' => 'Lab Jurnalistik']);

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.items.import'), [
                'file' => $this->csv(
                    'CAM-1,Kamera A,Kamera,Lab Jurnalistik,quantity,3,,,active,,',
                    'CAM-1,Kamera B,Kamera,Lab Jurnalistik,quantity,1,,,active,,',
                ),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Item::count());
    }

    public function test_non_admin_cannot_import(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'user']));

        $this->actingAs($user)
            ->post(route('admin.inventory.items.import'), [
                'file' => $this->csv('CAM-1,Kamera A,Kamera,Lab,quantity,1,,,active,,'),
            ])
            ->assertForbidden();

        $this->assertSame(0, Item::count());
    }
}
