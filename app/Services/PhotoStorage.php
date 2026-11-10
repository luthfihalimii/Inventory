<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PhotoStorage
{
    /**
     * Store uploaded condition photos on the public disk.
     *
     * @param  array<int, UploadedFile>  $photos
     * @return array<int, string> Stored paths relative to the disk.
     */
    public function store(array $photos, string $directory): array
    {
        $paths = [];

        foreach ($photos as $photo) {
            $path = Storage::disk('public')->putFile($directory, $photo);

            if ($path !== false) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
