<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = ReportBuilder::aggregate(
            $request->string('from')->toString(),
            $request->string('to')->toString(),
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}