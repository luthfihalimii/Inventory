<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('return_photos')) return;
        DB::table('return_photos')->orderBy('id')->chunkById(100, function ($photos) {
            foreach ($photos as $photo) {
                $path = ltrim((string) $photo->file_path, '/');
                if ($path === '' || str_contains($path, '..')) continue;
                if (Storage::disk('local')->exists($path)) continue;
                if (! Storage::disk('public')->exists($path)) continue;
                Storage::disk('local')->put($path, Storage::disk('public')->get($path));
                Storage::disk('public')->delete($path);
            }
        });
    }

    public function down(): void
    {
        // Files stay private intentionally; reversing this migration must not expose evidence publicly.
    }
};
