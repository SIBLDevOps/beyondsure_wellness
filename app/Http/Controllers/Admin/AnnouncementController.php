<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastEmails;
use App\Models\Announcement;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

/** Announcements to members: shown on their dashboard and, if the box is ticked, emailed to everyone they are meant for. */
class AnnouncementController extends Controller
{
    /** The announcements table and the email opt-out column come from a database update that may not have been run yet. */
    private function ready(): bool
    {
        return Schema::hasTable('announcements') && Schema::hasColumn('members', 'email_opt_out');
    }

    public function index(): View
    {
        if (! $this->ready()) {
            return view('admin.announcements.index', ['setupNeeded' => true]);
        }

        return view('admin.announcements.index', [
            'announcements' => Announcement::with('region')->latest()->paginate(15),
            'regions' => $this->allowedRegions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->ready()) {
            return redirect()->route('admin.announcements.index')->with('error', 'Nothing was posted: the database update for announcements has not been run yet.');
        }

        $allowed = $this->allowedRegions();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:100000', \App\Support\RichText::hasText(), \App\Support\RichText::maxText(3000)],
            'audience' => ['required', Rule::in(['all', 'active'])],
            'region_id' => ['nullable', 'integer', Rule::in($allowed->pluck('id')->all())],
        ], [
            'title.required' => 'Write a title for the announcement.',
            'body.required' => 'Write the announcement text.',
            'region_id.in' => 'You can only post to the regions you have access to.',
        ]);

        $data['body'] = \App\Support\RichText::clean($data['body']);

        // Region-limited staff can only address their own regions: with one region it is chosen for them.
        if ($request->user()->isRegionRestricted() && empty($data['region_id'])) {
            if ($allowed->count() !== 1) {
                return back()->withInput()->withErrors(['region_id' => 'Choose which of your regions this announcement is for.']);
            }
            $data['region_id'] = $allowed->first()->id;
        }

        $announcement = Announcement::create($data + [
            'send_email' => $request->boolean('send_email'),
            'created_by' => $request->user()->id,
        ]);

        if ($announcement->send_email) {
            SendBroadcastEmails::dispatch('announcement', $announcement->id);
        }

        return redirect()->route('admin.announcements.index')->with('status', $announcement->send_email
            ? 'Announcement posted and being emailed to members.'
            : 'Announcement posted. Members will see it on their dashboard.');
    }

    private function allowedRegions()
    {
        return Region::orderBy('sort_order')->get();
    }
}
