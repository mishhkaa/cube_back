<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function lastNotices(): JsonResponse
    {
        $res = Notice::whereType('dashboard')->latest()->get();

        return response()->json($res);
    }
}
