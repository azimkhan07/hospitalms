<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function index()
    {
        // echo 'done';exit;
        return view("admins.login");
    }

    public function authenticate_admin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {

            if (! hms_role_enabled(Auth::user()->roleSlug())) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->with('error', 'This role is not available in the current institution mode.');
            }

            $request->session()->regenerate();

            return redirect()->route('admin_dashboard');
        }

        return back()->with('error', 'Invalid email or password');
    }
}
