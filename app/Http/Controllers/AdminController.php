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

            $request->session()->regenerate();

            return redirect()->route('admin_dashboard');
        }

        return back()->with('error', 'Invalid email or password');
    }
}
