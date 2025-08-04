<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // Added for logging

class ApprovalRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $httpRequest)
    {
        $user = Auth::user();
        $requests = collect();

        // Apply filter if specified (e.g., from Dashboard 'My Submitted Requests')
        $filterByUser = $httpRequest->query('filter_by_user');

        if ($user->hasRole('admin')) {
            $requests = ApprovalRequest::with('user')->latest();
            if ($filterByUser) { // Admin can view specific user's requests
                $requests->where('user_id', $filterByUser);
            }
            $requests = $requests->get();
        } elseif ($user->hasRole('requester')) {
            $requests = ApprovalRequest::where('user_id', $user->id)->with('user')->latest()->get();
        } elseif ($user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer'])) {
            $roleStatusMap = [
                'procurement' => 'pending_procurement',
                'accountant' => 'pending_accountant',
                'program_coordinator' => 'pending_coordinator',
                'chief_officer' => 'pending_chief_officer',
            ];

            $roleColumnMap = [
                'procurement' => 'procurement_approved_at',
                'accountant' => 'accountant_approved_at',
                'program_coordinator' => 'coordinator_approved_at', // This maps 'program_coordinator' role to 'coordinator_approved_at' column
                'chief_officer' => 'chief_officer_approved_at',
            ];

            $currentRole = $user->getRoleNames()->first();
            $pendingStatus = $roleStatusMap[$currentRole] ?? null;
            $approvedAtColumn = $roleColumnMap[$currentRole] ?? null;

            if ($pendingStatus && $approvedAtColumn) {
                $requests = ApprovalRequest::where(function ($query) use ($pendingStatus, $approvedAtColumn, $user) {
                    $query->where('status', $pendingStatus)
                        ->orWhere(function ($q) use ($approvedAtColumn) {
                            $q->where('final_outcome', '!=', null)
                                ->orWhere($approvedAtColumn, '!=', null);
                        })
                        ->orWhere('user_id', $user->id); // Approvers can see their own requests too
                })->with('user')->latest()->get();
            } else {
                // Fallback for roles that don't fit the approval flow or if no specific status is mapped
                $requests = ApprovalRequest::where('user_id', $user->id)->with('user')->latest()->get();
            }
        }

        return view('requests.index', ['requests' => $requests]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Only requesters can create requests
        if (!Auth::user()->hasRole('requester')) {
            return redirect()->route('dashboard')->withErrors('You are not authorized to create requests.');
        }
        return view('requests.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $httpRequest)
    {
        // Only requesters can store requests
        if (!Auth::user()->hasRole('requester')) {
            return redirect()->route('dashboard')->withErrors('You are not authorized to submit requests.');
        }

        $validatedData = $httpRequest->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'estimated_cost' => 'required|numeric|min:0',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:2048',
        ]);

        $attachmentPath = null;
        if ($httpRequest->hasFile('attachment')) {
            $attachmentPath = $httpRequest->file('attachment')->store('attachments', 'public');
        }

        Auth::user()->requests()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'estimated_cost' => $validatedData['estimated_cost'],
            'attachment_path' => $attachmentPath,
            'status' => 'pending_procurement',
            'current_approver_role' => 'procurement',
        ]);

        return redirect()->route('dashboard')->with('success', 'Request submitted successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $approvalRequest = ApprovalRequest::find($id);
        if (!$approvalRequest) {
            return redirect()->route('dashboard')->withErrors('Request not found.');
        }

        $user = Auth::user();

        // Authorization logic for viewing a request
        // Admin can view any request.
        // Requester can view their own request.
        // Approvers can view requests if they are (or were) involved in its approval flow OR if the request is rejected/approved.
        if (
            !$user->hasRole('admin') && // Not an admin
            $user->id !== $approvalRequest->user_id && // Not the requester
            !(
                // Approver's current or past involvement
                ($user->hasRole('procurement') && ($approvalRequest->status == 'pending_procurement' || $approvalRequest->procurement_approved_at || $approvalRequest->current_approver_role == 'procurement')) ||
                ($user->hasRole('accountant') && ($approvalRequest->status == 'pending_accountant' || $approvalRequest->accountant_approved_at || $approvalRequest->current_approver_role == 'accountant')) ||
                ($user->hasRole('program_coordinator') && ($approvalRequest->status == 'pending_coordinator' || $approvalRequest->coordinator_approved_at || $approvalRequest->current_approver_role == 'program_coordinator')) ||
                ($user->hasRole('chief_officer') && ($approvalRequest->status == 'pending_chief_officer' || $approvalRequest->chief_officer_approved_at || $approvalRequest->current_approver_role == 'chief_officer'))
            ) &&
            !(
                // Allow viewing if request is rejected or approved, for relevant roles
                ($approvalRequest->final_outcome == 'rejected' && ($user->id === $approvalRequest->user_id || $user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer']))) ||
                ($approvalRequest->final_outcome == 'approved' && ($user->id === $approvalRequest->user_id || $user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer'])))
            ) &&
            !(
                // Allow viewing if request is sent back to requester, for relevant roles
                ($approvalRequest->final_outcome == 'sent_back' && ($user->id === $approvalRequest->user_id || $user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer'])))
            )
        ) {
            return redirect()->route('dashboard')->withErrors('You are not authorized to view this request.');
        }

        return view('requests.show', ['request' => $approvalRequest]);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $approvalRequest = ApprovalRequest::find($id);
        if (!$approvalRequest) {
            return redirect()->route('dashboard')->withErrors('Request not found for editing.');
        }

        $user = Auth::user();

        // Authorization logic for editing a request
        if ($user->hasRole('admin')) {
            // Admin can edit any request regardless of status
        } elseif (
            $user->id === $approvalRequest->user_id && // Must be the requester
            ($approvalRequest->status === 'pending_procurement' || $approvalRequest->status === 'sent_back_to_requester') // Requester can only edit if status allows
        ) {
            // Requester can edit their own request if it's pending procurement or sent back
        } else {
            // If neither admin nor authorized requester
            return redirect()->route('dashboard')->withErrors('You are not authorized to edit this request or it is no longer editable.');
        }

        return view('requests.edit', ['request' => $approvalRequest]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $httpRequest, $id)
    {
        $approvalRequest = ApprovalRequest::find($id);
        if (!$approvalRequest) {
            return redirect()->route('dashboard')->withErrors('Request not found for updating.');
        }

        $user = Auth::user();

        // Authorization logic for updating a request (should mirror edit authorization)
        if ($user->hasRole('admin')) {
            // Admin can update any request regardless of status
        } elseif (
            $user->id === $approvalRequest->user_id && // Must be the requester
            ($approvalRequest->status === 'pending_procurement' || $approvalRequest->status === 'sent_back_to_requester') // Requester can only update if status allows
        ) {
            // Requester can update their own request if it's pending procurement or sent back
        } else {
            // If neither admin nor authorized requester
            return redirect()->route('dashboard')->withErrors('You are not authorized to update this request or it is no longer editable.');
        }

        $validatedData = $httpRequest->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'estimated_cost' => 'required|numeric|min:0',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:2048',
        ]);

        $attachmentPath = $approvalRequest->attachment_path;
        if ($httpRequest->hasFile('attachment')) {
            if ($attachmentPath && Storage::disk('public')->exists($attachmentPath)) {
                Storage::disk('public')->delete($attachmentPath);
            }
            $attachmentPath = $httpRequest->file('attachment')->store('attachments', 'public');
        }

        $approvalRequest->update([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'estimated_cost' => $validatedData['estimated_cost'],
            'attachment_path' => $attachmentPath,
            'status' => 'pending_procurement', // When updated, it goes back to initial approval stage
            'current_approver_role' => 'procurement',
            'procurement_approved_at' => null, // Reset all approval timestamps and comments
            'accountant_approved_at' => null,
            'coordinator_approved_at' => null,
            'chief_officer_approved_at' => null,
            'procurement_comments' => null,
            'accountant_comments' => null,
            'coordinator_comments' => null,
            'chief_officer_comments' => null,
            'final_outcome' => null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Request updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $approvalRequest = ApprovalRequest::find($id);
        if (!$approvalRequest) {
            return redirect()->route('dashboard')->withErrors('Request not found for deletion.');
        }

        $user = Auth::user();

        // Authorization for deleting a request
        if ($user->hasRole('admin')) {
            // Admin can delete any request
        } elseif (
            $user->id === $approvalRequest->user_id && // Must be the requester
            ($approvalRequest->status === 'pending_procurement' || $approvalRequest->status === 'sent_back_to_requester') // Requester can delete their own if status allows
        ) {
            // Requester can delete their own request if it's pending procurement or sent back
        } else {
            // If neither admin nor authorized requester
            return redirect()->route('dashboard')->withErrors('You are not authorized to delete this request.');
        }

        if ($approvalRequest->attachment_path && Storage::disk('public')->exists($approvalRequest->attachment_path)) {
            Storage::disk('public')->delete($approvalRequest->attachment_path);
        }

        $approvalRequest->delete();

        return redirect()->route('dashboard')->with('success', 'Request deleted successfully!');
    }


    /**
     * Handle the approval process for a request.
     */
    public function approve(Request $httpRequest, $id)
    {
        $user = Auth::user();
        $currentRole = $user->getRoleNames()->first(); // Get the primary role of the current user

        $comments = $httpRequest->input('comments');
        $action = $httpRequest->input('action');


        $approvalRequest = ApprovalRequest::find($id);
        if (!$approvalRequest) {
            return back()->withErrors('Request not found.');
        }


        // Reject action
        if (str_contains($action, 'reject_')) {
            // Determine the role whose timestamp/comments should be set for rejection
            $roleBeingRejected = str_replace('reject_', '', $action);
            $rejectCommentsField = $roleBeingRejected . '_comments';

            // Ensure only the current approver or admin can reject
            if (!$user->hasRole('admin') && $approvalRequest->current_approver_role !== $currentRole) {
                return back()->withErrors('You are not authorized to reject this request at this stage.');
            }

            $approvalRequest->update([
                'status' => 'rejected',
                'final_outcome' => 'rejected',
                $rejectCommentsField => $comments,
                'current_approver_role' => null,
            ]);
            return back()->with('success', 'Request rejected.');
        }

        // Send Back to Requester action
        if (str_contains($action, 'send_back')) {
             // Determine the role whose timestamp/comments should be set for sending back
            $roleSendingBack = str_replace('send_back_', '', $action);
            $sendBackCommentsField = $roleSendingBack . '_comments';

            // Ensure only the current approver or admin can send back
            if (!$user->hasRole('admin') && $approvalRequest->current_approver_role !== $currentRole) {
                return back()->withErrors('You are not authorized to send back this request at this stage.');
            }

            $approvalRequest->update([
                'status' => 'sent_back_to_requester',
                'final_outcome' => 'sent_back',
                $sendBackCommentsField => $comments, // Use the dynamically determined comments field
                'current_approver_role' => null,
            ]);
            return back()->with('success', 'Request sent back to requester for modifications.');
        }


        // Define the columns for approved_at and comments based on the current role.
        // This is crucial for *non-admin* approvals to correctly set the timestamp.
        $currentApprovedAtField = '';
        $currentCommentsField = '';

        // Correctly map the role to its respective database column names
        // Note: For 'program_coordinator' role, the column is 'coordinator_approved_at'.
        // This is a manual mapping because the role name doesn't directly match the column prefix.
        if ($currentRole === 'procurement') {
            $currentApprovedAtField = 'procurement_approved_at';
            $currentCommentsField = 'procurement_comments';
        } elseif ($currentRole === 'accountant') {
            $currentApprovedAtField = 'accountant_approved_at';
            $currentCommentsField = 'accountant_comments';
        } elseif ($currentRole === 'program_coordinator') {
            $currentApprovedAtField = 'coordinator_approved_at'; // Specific column for program_coordinator
            $currentCommentsField = 'coordinator_comments'; // Specific column for program_coordinator
        } elseif ($currentRole === 'chief_officer') {
            $currentApprovedAtField = 'chief_officer_approved_at';
            $currentCommentsField = 'chief_officer_comments';
        } else {
            // Fallback or error for unhandled roles trying to approve
            return back()->withErrors('Your role is not configured for this approval action.');
        }


        // Approval flow definition
        $flow = [
            'procurement' => [
                'current_status' => 'pending_procurement',
                'next_status' => 'pending_accountant',
                'next_approver_role' => 'accountant',
            ],
            'accountant' => [
                'current_status' => 'pending_accountant',
                'next_status' => 'pending_coordinator',
                'next_approver_role' => 'program_coordinator',
            ],
            'program_coordinator' => [
                'current_status' => 'pending_coordinator',
                'next_status' => 'pending_chief_officer',
                'next_approver_role' => 'chief_officer',
            ],
            'chief_officer' => [
                'current_status' => 'pending_chief_officer',
                'next_status' => 'approved',
                'next_approver_role' => null,
            ],
        ];

        $isAuthorized = false;
        $nextStatus = null;
        $nextApproverRole = null;

        // Check for regular role-based authorization
        if (isset($flow[$currentRole]) && $approvalRequest->status === $flow[$currentRole]['current_status']) {
            $isAuthorized = true;
            $nextStatus = $flow[$currentRole]['next_status'];
            $nextApproverRole = $flow[$currentRole]['next_approver_role'];
        }
        // Admin approval override
        elseif ($user->hasRole('admin') && str_starts_with($approvalRequest->status, 'pending_')) {
            $isAuthorized = true;
            $currentPendingRole = str_replace('pending_', '', $approvalRequest->status);

            // Re-map fields based on the *actual* pending role for Admin override
            if ($currentPendingRole === 'procurement') {
                $currentApprovedAtField = 'procurement_approved_at';
                $currentCommentsField = 'procurement_comments';
            } elseif ($currentPendingRole === 'accountant') {
                $currentApprovedAtField = 'accountant_approved_at';
                $currentCommentsField = 'accountant_comments';
            } elseif ($currentPendingRole === 'coordinator') { // Note: 'coordinator' for DB column, not 'program_coordinator' role
                $currentApprovedAtField = 'coordinator_approved_at';
                $currentCommentsField = 'coordinator_comments';
            } elseif ($currentPendingRole === 'chief_officer') {
                $currentApprovedAtField = 'chief_officer_approved_at';
                $currentCommentsField = 'chief_officer_comments';
            }

            if ($approvalRequest->status === 'pending_procurement') {
                $nextStatus = 'pending_accountant';
                $nextApproverRole = 'accountant';
            } elseif ($approvalRequest->status === 'pending_accountant') {
                $nextStatus = 'pending_coordinator';
                $nextApproverRole = 'program_coordinator';
            } elseif ($approvalRequest->status === 'pending_coordinator') {
                $nextStatus = 'pending_chief_officer';
                $nextApproverRole = 'chief_officer';
            } elseif ($approvalRequest->status === 'pending_chief_officer') {
                $nextStatus = 'approved';
                $nextApproverRole = null;
            }
        }


        if ($isAuthorized) {
            $updateData = [
                'status' => $nextStatus,
                'current_approver_role' => $nextApproverRole,
                $currentCommentsField => $comments,
                $currentApprovedAtField => now(),
            ];

            if ($nextStatus === 'approved') {
                $updateData['final_outcome'] = 'approved';
            }

            $approvalRequest->update($updateData);
            return back()->with('success', 'Request approved and moved to the next stage.');
        }

        return back()->withErrors('Action could not be processed. Please check the request status or your role.');
    }

    /**
     * Handle file download for attachments.
     */
    public function downloadAttachment($id)
    {
        $approvalRequest = ApprovalRequest::find($id);
        if (!$approvalRequest) {
            return back()->withErrors('Request not found.');
        }

        if (!$approvalRequest->attachment_path || !Storage::disk('public')->exists($approvalRequest->attachment_path)) {
            return back()->withErrors('File not found.');
        }

        $user = Auth::user();
        // Authorization to download: Requester, Admin, or any approver who has or had the request
        if (
            $user->id !== $approvalRequest->user_id && // Not the requester
            !$user->hasRole('admin') && // Not an admin
            !( // Check if user has relevant role AND the request was handled by or is pending with them
                ($user->hasRole('procurement') && ($approvalRequest->procurement_approved_at || $approvalRequest->status == 'pending_procurement' || $approvalRequest->current_approver_role == 'procurement')) ||
                ($user->hasRole('accountant') && ($approvalRequest->accountant_approved_at || $approvalRequest->status == 'pending_accountant' || $approvalRequest->current_approver_role == 'accountant')) ||
                ($user->hasRole('program_coordinator') && ($approvalRequest->coordinator_approved_at || $approvalRequest->status == 'pending_coordinator' || $approvalRequest->current_approver_role == 'program_coordinator')) ||
                ($user->hasRole('chief_officer') && ($approvalRequest->chief_officer_approved_at || $approvalRequest->status == 'pending_chief_officer' || $approvalRequest->current_approver_role == 'chief_officer'))
            ) &&
            // Added condition for viewing rejected/approved/sent_back requests
            !(
                ($approvalRequest->final_outcome == 'rejected' && ($user->id === $approvalRequest->user_id || $user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer']))) ||
                ($approvalRequest->final_outcome == 'approved' && ($user->id === $approvalRequest->user_id || $user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer']))) ||
                ($approvalRequest->final_outcome == 'sent_back' && ($user->id === $approvalRequest->user_id || $user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer'])))
            )
        ) {
            return redirect()->route('requests.show', $approvalRequest)->withErrors('You are not authorized to download this attachment.');
        }

        return Storage::disk('public')->download($approvalRequest->attachment_path);
    }
}
