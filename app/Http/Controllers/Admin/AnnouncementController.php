<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-announcements');

        return Inertia::render('admin/announcements/Index', [
            'announcements' => Announcement::query()
                ->with('creator:id,name')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-announcements');

        $data = $this->validatePayload($request);

        $announcement = Announcement::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => 'draft',
            'created_by_user_id' => $request->user()->id,
        ]);

        $audit->record('announcement.created', $announcement, ['title' => $announcement->title]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman dibuat sebagai draft.']);

        return back();
    }

    public function update(Request $request, Announcement $announcement, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-announcements');

        $data = $this->validatePayload($request);
        $before = $announcement->only(['title', 'content']);

        $announcement->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'updated_by_user_id' => $request->user()->id,
        ]);

        $audit->record(
            'announcement.updated',
            $announcement,
            [],
            $before,
            $announcement->only(['title', 'content']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman diperbarui.']);

        return back();
    }

    public function publish(Request $request, Announcement $announcement, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-announcements');

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        $before = ['status' => $announcement->status];

        $announcement->update([
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published'
                ? ($announcement->published_at ?? now())
                : $announcement->published_at,
            'updated_by_user_id' => $request->user()->id,
        ]);

        if ($data['status'] === 'published') {
            $audit->record(
                'announcement.published',
                $announcement,
                ['title' => $announcement->title],
                $before,
                ['status' => 'published'],
            );
        } else {
            $audit->record(
                'announcement.unpublished',
                $announcement,
                ['title' => $announcement->title],
                $before,
                ['status' => 'draft'],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status pengumuman diperbarui.']);

        return back();
    }

    /**
     * @return array{title: string, content: string}
     */
    private function validatePayload(Request $request): array
    {
        /** @var array{title: string, content: string} $data */
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:20000'],
        ]);

        return $data;
    }
}
