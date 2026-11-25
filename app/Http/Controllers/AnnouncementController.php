<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ReturnPhoto;
use App\Support\AnnouncementHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $isManager = $request->user()->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();
        $query = Announcement::with('creator')->where(function ($q) {
            $q->where('status', 'PUBLISHED')->where(function ($published) {
                $published->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
        });
        if ($isManager) $query = Announcement::with('creator');

        return view('announcements.index', [
            'announcements' => $query->orderByDesc('published_at')->orderByDesc('created_at')->paginate(12)->withQueryString(),
            'isManager' => $isManager,
        ]);
    }

    public function create(): View { return view('announcements.create'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['dibuat_oleh'] = $request->user()->id;
        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['isi'] = AnnouncementHtmlSanitizer::clean($data['isi']);
        if (trim(strip_tags($data['isi'])) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['isi' => 'Isi pengumuman harus memuat teks yang dapat dibaca.']);
        }
        $data['published_at'] = $this->publicationTime($data);
        $announcement = Announcement::create($data);
        $this->writeAudit('CREATE', 'Announcement', $announcement->id, null, $announcement->toArray());
        return redirect()->route('announcements.index')->with('success', 'Pengumuman berhasil disimpan.');
    }

    public function edit(Announcement $announcement): View { return view('announcements.edit', compact('announcement')); }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request);
        $data['isi'] = AnnouncementHtmlSanitizer::clean($data['isi']);
        if (trim(strip_tags($data['isi'])) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['isi' => 'Isi pengumuman harus memuat teks yang dapat dibaca.']);
        }
        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['published_at'] = $this->publicationTime($data);
        $old = $announcement->toArray();
        $announcement->update($data);
        $this->writeAudit('UPDATE', 'Announcement', $announcement->id, $old, $announcement->fresh()->toArray());
        return redirect()->route('announcements.index')->with('success', 'Pengumuman diperbarui.');
    }

    public function deactivate(Announcement $announcement): RedirectResponse
    {
        $old = $announcement->only(['status']);
        $announcement->update(['status' => 'INACTIVE']);
        $this->writeAudit('DEACTIVATE', 'Announcement', $announcement->id, $old, ['status' => 'INACTIVE']);
        return back()->with('success', 'Pengumuman dinonaktifkan.');
    }

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => ['required','image','mimes:jpg,jpeg,png,webp','max:2048']]);
        $file = $request->file('image');
        $extension = strtolower($file->extension());
        $name = (string) \Illuminate\Support\Str::uuid().'.'.$extension;
        $stored = Storage::disk('local')->putFileAs('announcements', $file, $name);
        abort_unless($stored, 500, 'Gambar gagal disimpan.');
        return response()->json(['url' => route('announcements.images.show', ['filename' => $name])], 201);
    }

    public function showImage(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9-]+\.(jpg|jpeg|png|webp)$/iD', $filename), 404);
        $path = 'announcements/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);
        $fullPath = Storage::disk('local')->path($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fullPath);
        abort_unless(in_array($mime, ['image/jpeg','image/png','image/webp'], true), 404);
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'judul' => ['required','string','max:180'],
            'isi' => ['required','string','max:200000'],
            'status' => ['required', Rule::in(['DRAFT','PUBLISHED','INACTIVE'])],
            'published_at' => ['nullable','date'],
            'kategori' => ['required', Rule::in(['INFORMASI','OPERASIONAL','PEMELIHARAAN','KEBIJAKAN'])],
            'is_pinned' => ['nullable','boolean'],
        ]);
    }

    private function publicationTime(array $data): mixed
    {
        if ($data['status'] !== 'PUBLISHED') return $data['published_at'] ?? null;
        return ! empty($data['published_at']) ? $data['published_at'] : now();
    }
}
