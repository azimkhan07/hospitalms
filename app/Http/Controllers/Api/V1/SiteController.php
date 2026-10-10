<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'site' => SiteContent::get(),
                'departments' => SiteContent::departments(),
                'doctors' => SiteContent::doctors(),
                'medicines' => SiteContent::medicines()->take(12)->values(),
                'features' => SiteContent::features(),
                'testimonials' => SiteContent::testimonials(),
            ],
        ]);
    }

    public function doctors(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SiteContent::doctors(),
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SiteContent::departments(),
        ]);
    }
}
