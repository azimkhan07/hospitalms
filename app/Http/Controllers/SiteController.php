<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        return view('index', [
            'site' => SiteContent::get(),
            'departments' => SiteContent::departments(),
            'doctors' => SiteContent::doctors(),
        ]);
    }

    public function about(): View
    {
        return view('about', [
            'site' => SiteContent::get(),
            'departments' => SiteContent::departments(),
        ]);
    }

    public function contact(): View
    {
        return view('contact', [
            'site' => SiteContent::get(),
            'departments' => SiteContent::departments(),
        ]);
    }

    public function doctors(): View
    {
        return view('docter', [
            'site' => SiteContent::get(),
            'doctors' => SiteContent::doctors(),
        ]);
    }

    public function services(): View
    {
        return view('services', [
            'site' => SiteContent::get(),
            'departments' => SiteContent::departments(),
            'medicines' => SiteContent::medicines()->take(12),
        ]);
    }
}