<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\LoanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        $isAdmin = $request->user()->roles()->where('nama', 'ADMIN')->exists();
        $isSuperAdmin = $request->user()->roles()->where('nama', 'SUPER_ADMIN')->exists();

        abort_unless(
            $isAdmin || $isSuperAdmin || $loanRequest->user_id === $request->user()->id,
            403
        );

        $data = $request->validate([
            'isi' => ['required', 'string', 'max:5000'],
        ]);

        $comment = Comment::create([
            'loan_request_id' => $loanRequest->id,
            'user_id' => $request->user()->id,
            'isi' => $data['isi'],
        ]);

        $this->writeAudit('CREATE', 'Comment', $comment->id, null, [
            'loan_request_id' => $loanRequest->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Komentar berhasil dikirim.');
    }
}
