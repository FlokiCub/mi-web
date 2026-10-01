<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\User;
use App\Models\WhatsAppNotification;
use App\Services\DirectorDashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DirectorDashboardService $dashboardService) {}

    /**
     * Display admin dashboard with full executive metrics.
     */
    public function index()
    {
        $metrics = $this->dashboardService->getExecutiveMetrics();

        // User statistics for dashboard
        $totalUsers = User::count();
        $adminCount = User::where('role', 'director')->count();
        $standardUserCount = User::where('role', '!=', 'director')->count();
        $activeUserCount = User::where('is_active', true)->count();
        $recentUsers = User::latest()->take(5)->get();

        $recentEquipments = Equipment::with('currentWarehouse')->latest()->take(6)->get();
        $recentNotifications = WhatsAppNotification::with('equipment')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'metrics',
            'totalUsers',
            'adminCount',
            'standardUserCount',
            'activeUserCount',
            'recentUsers',
            'recentEquipments',
            'recentNotifications'
        ));
    }
}
