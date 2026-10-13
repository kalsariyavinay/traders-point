<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'totalUsers' => User::where('role', UserRole::User)->count(),
            'totalVideos' => Video::count(),
            'totalOrders' => Order::count(),
            'totalRevenue' => (float) Order::successful()->sum('amount'),
            'recentOrders' => Order::with(['user', 'video'])
                ->successful()
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
