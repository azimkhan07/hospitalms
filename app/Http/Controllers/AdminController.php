<?php

namespace App\Http\Controllers;

use App\Services\AttendanceRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function index()
    {
        return view('admins.login');
    }

    public function authenticate_admin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return back()->with('error', 'Invalid email or password');
        }

        $user = Auth::user();

        if (! hms_role_enabled($user->roleSlug())) {
            return $this->reject($request, 'This role is not available in the current institution mode.');
        }

        // Staff may only sign in from the hospital premises. The admin is exempt
        // because they run the system and may work remotely.
        if ($geoError = hms_geo_check($user, $request->input('latitude') !== null ? (float) $request->input('latitude') : null, $request->input('longitude') !== null ? (float) $request->input('longitude') : null)) {
            return $this->reject($request, $geoError);
        }

        $request->session()->regenerate();

        // Presence starts at sign-in and closes on logout.
        app(AttendanceRecorder::class)->checkIn(
            $user,
            $request->input('latitude') !== null ? (float) $request->input('latitude') : null,
            $request->input('longitude') !== null ? (float) $request->input('longitude') : null,
            $request->ip()
        );

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('admin_dashboard');
    }

    /**
     * Undo a successful auth attempt and bounce back to the form.
     */
    private function reject(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return back()->with('error', $message)->withInput($request->only('email'));
    }
}
