<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * Admin announcement routes are protected by the 'admin' middleware at the route level.
 * The activeAnnouncements method is accessible to all authenticated users.
 */
class AdminAnnouncementController extends Controller
{
    /**
     * Active announcements for authenticated users (non-expired only).
     */
    public function activeAnnouncements()
    {
        return response()->json(Announcement::where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->orderBy('created_at', 'desc')->get());
    }

    public function announcements(Request $request)
    {
        return response()->json(Announcement::orderBy('created_at', 'desc')->get());
    }

    public function createAnnouncement(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'severity' => 'nullable|in:info,warning,error,success',
            'expires_at' => 'nullable|date',
        ]);
        $ann = Announcement::create(array_merge(
            $request->only(['title', 'message', 'severity', 'expires_at']),
            ['created_by' => $request->user()->id, 'active' => true]
        ));

        return response()->json($ann, 201);
    }

    public function updateAnnouncement(Request $request, string $id)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'severity' => 'sometimes|in:info,warning,error,success',
            'active' => 'sometimes|boolean',
            'expires_at' => 'sometimes|nullable|date',
        ]);
        $ann = Announcement::findOrFail($id);
        $ann->update($request->only(['title', 'message', 'severity', 'active', 'expires_at']));

        return response()->json($ann);
    }

    public function deleteAnnouncement(Request $request, string $id)
    {
        Announcement::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
