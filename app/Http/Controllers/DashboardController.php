<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ApprovalRequest;

class DashboardController extends Controller
{
    /**
     * Display the dashboard view.
     */
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        // Initialize counts
        $submittedRequestsCount = 0;
        $pendingApprovalsCount = 0;
        $totalRequestsCount = 0; // For admin

        // Logic for all users (e.g., how many requests they submitted)
        if ($user->hasRole('requester')) {
            $submittedRequestsCount = ApprovalRequest::where('user_id', $user->id)->count();
        }

        // Logic for approvers and admin
        if ($user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer', 'admin'])) {
            if ($user->hasRole('admin')) {
                $totalRequestsCount = ApprovalRequest::count();
                $pendingApprovalsCount = ApprovalRequest::whereIn('status', [
                    'pending_procurement',
                    'pending_accountant',
                    'pending_coordinator',
                    'pending_chief_officer'
                ])->count();
            } else {
                // Specific pending approvals for the current approver
                $roleStatusMap = [
                    'procurement' => 'pending_procurement',
                    'accountant' => 'pending_accountant',
                    'program_coordinator' => 'pending_coordinator',
                    'chief_officer' => 'pending_chief_officer',
                ];

                $currentRole = $user->getRoleNames()->first(); // Get the primary role

                if (isset($roleStatusMap[$currentRole])) {
                    $pendingApprovalsCount = ApprovalRequest::where('current_approver_role', $currentRole)->count();
                }
            }
        }

        return view('dashboard', compact('submittedRequestsCount', 'pendingApprovalsCount', 'totalRequestsCount'));
    }
}
