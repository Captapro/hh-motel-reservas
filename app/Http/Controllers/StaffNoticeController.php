<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyHostView;
use App\Models\Staff;
use App\Models\StaffNotice;
use App\Models\StaffNoticeRead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lo que consultan los paneles del personal para abrir los avisos y confirmarlos. */
class StaffNoticeController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $viewer = $this->viewer($request);

        return response()->json($viewer ? StaffNotice::pendingFor($viewer)->map(fn (StaffNotice $notice) => [
            'id' => $notice->id,
            'body' => $notice->body,
            'urgent' => $notice->urgent,
            'sender' => $notice->sender?->name,
            'at' => $notice->created_at->timezone('America/Santiago')->format('H:i'),
        ])->values() : []);
    }

    public function read(Request $request, StaffNotice $notice): JsonResponse
    {
        $viewer = $this->viewer($request);
        abort_unless($viewer && StaffNotice::pendingFor($viewer)->contains('id', $notice->id), 404);

        StaffNoticeRead::create([
            'staff_notice_id' => $notice->id,
            $viewer instanceof User ? 'user_id' : 'staff_id' => $viewer->id,
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** La cuenta con sesión o la mucama de este celular. La vista de anfitrión de soporte no cuenta. */
    private function viewer(Request $request): User|Staff|null
    {
        $user = $request->user();
        if ($user?->isRecepcion() || $user?->isAdministrador()) {
            return $request->session()->get(ApplyHostView::SESSION_KEY) ? null : $user;
        }

        $staffId = $request->session()->get('mucama_staff_id');

        return $staffId ? Staff::activeMucamas()->find($staffId) : null;
    }
}
