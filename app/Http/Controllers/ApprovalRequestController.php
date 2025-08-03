<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ApprovalRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $httpRequest)
    {
        $user = Auth::user();
        $requests = collect();

        $filterByUser = $httpRequest->query('filter_by_user');

        if ($user->hasRole('admin')) {
            $requests = ApprovalRequest::with('user')->latest();
            if ($filterByUser) {
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
                'program_coordinator' => 'coordinator_approved_at',
                'chief_officer' => 'chief_officer_approved_at',
            ];

            $currentRole = $user->getRoleNames()->first();
            $pendingStatus = $roleStatusMap[$currentRole] ?? null;
            $approvedAtColumn = $roleColumnMap[$currentRole] ?? null;

            if ($pendingStatus && $approvedAtColumn) {
                $requests = ApprovalRequest::where(function ($query) use ($pendingStatus, $approvedAtColumn, $user, $currentRole) {
                    $query->where('status', $pendingStatus) // Requests currently pending this role's approval
                          ->orWhere($approvedAtColumn, '!=', null) // Requests this role has approved
                          ->orWhere('final_outcome', '!=', null); // Requests that have reached a final outcome (approved, rejected, sent back)

                    // Additionally, for coordinator and above, show requests that are 'approved' (final stage)
                    // and requests pending a later stage (e.g., coordinator sees pending chief officer)
                    if ($currentRole === 'program_coordinator') {
                        $query->orWhere('status', 'pending_chief_officer')
                              ->orWhere('status', 'approved')
                              ->orWhere('status', 'rejected'); // Ensure they also see rejected requests from later stages
                    } elseif ($currentRole === 'accountant') {
                        $query->orWhere('status', 'pending_coordinator')
                              ->orWhere('status', 'pending_chief_officer')
                              ->orWhere('status', 'approved')
                              ->orWhere('status', 'rejected');
                    } elseif ($currentRole === 'procurement') {
                        $query->orWhere('status', 'pending_accountant')
                              ->orWhere('status', 'pending_coordinator')
                              ->orWhere('status', 'pending_chief_officer')
                              ->orWhere('status', 'approved')
                              ->orWhere('status', 'rejected');
                    }
                    // For any role, they should also see their own submitted requests
                    $query->orWhere('user_id', $user->id);

                })->with('user')->latest()->get();
            } else {
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
        return view('requests.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $httpRequest)
    {
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

        // Simplified Authorization for viewing:
        // Admin can view any request.
        // Requester can view their own requests.
        // Any role in the approval flow can view any request (as they are part of the process).
        if ($user->hasRole('admin') ||
            $user->id === $approvalRequest->user_id ||
            $user->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer'])) {
            // Authorized to view
        } else {
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

        if (
            ($user->id !== $approvalRequest->user_id && !$user->hasRole('admin')) ||
            !($approvalRequest->status == 'pending_procurement' || $approvalRequest->status == 'sent_back_to_requester')
        ) {
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

        if (
            ($user->id !== $approvalRequest->user_id && !$user->hasRole('admin')) ||
            !($approvalRequest->status == 'pending_procurement' || $approvalRequest->status == 'sent_back_to_requester')
        ) {
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
            'status' => 'pending_procurement',
            'current_approver_role' => 'procurement',
            'procurement_approved_at' => null,
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

        if (
            ($user->id !== $approvalRequest->user_id && !$user->hasRole('admin')) ||
            !($approvalRequest->status == 'pending_procurement' || $approvalRequest->status == 'sent_back_to_requester' || $user->hasRole('admin'))
        ) {
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
        $approvalRequest = ApprovalRequest::find($id);
        if (!$approvalRequest) {
            return back()->withErrors('Request not found.');
        }

        $user = Auth::user();
        $comments = $httpRequest->input('comments');
        $action = $httpRequest->input('action');

        $currentRole = $user->getRoleNames()->first();
        $nextStatus = null;
        $nextApproverRole = null;
        $currentApprovedAtField = $currentRole . '_approved_at';
        $currentCommentsField = $currentRole . '_comments';

        // Override for program_coordinator as its database column is 'coordinator_approved_at'
        if ($currentRole === 'program_coordinator') {
            $currentApprovedAtField = 'coordinator_approved_at';
            $currentCommentsField = 'coordinator_comments';
        }


        if ($action === 'reject') {
            $approvalRequest->update([
                'status' => 'rejected',
                'final_outcome' => 'rejected',
                $currentCommentsField => $comments,
                $currentApprovedAtField => now(),
                'current_approver_role' => null,
            ]);
            return back()->with('success', 'Request rejected.');
        }

        if ($action === 'send_back_to_requester') {
            $approvalRequest->update([
                'status' => 'sent_back_to_requester',
                'final_outcome' => 'sent_back',
                $currentCommentsField => $comments,
                $currentApprovedAtField => now(),
                'current_approver_role' => null,
            ]);
            return back()->with('success', 'Request sent back to requester for modifications.');
        }


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
        if (isset($flow[$currentRole]) && $approvalRequest->status === $flow[$currentRole]['current_status']) {
            $isAuthorized = true;
            $nextStatus = $flow[$currentRole]['next_status'];
            $nextApproverRole = $flow[$currentRole]['next_approver_role'];
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
        if (
            $user->id !== $approvalRequest->user_id &&
            !$user->hasRole('admin') &&
            !($user->hasRole('procurement') && ($approvalRequest->procurement_approved_at || $approvalRequest->status == 'pending_procurement' || $approvalRequest->current_approver_role == 'procurement')) &&
            !($user->hasRole('accountant') && ($approvalRequest->accountant_approved_at || $approvalRequest->status == 'pending_accountant' || $approvalRequest->current_approver_role == 'accountant')) &&
            !($user->hasRole('program_coordinator') && ($approvalRequest->coordinator_approved_at || $approvalRequest->status == 'pending_coordinator' || $approvalRequest->current_approver_role == 'program_coordinator')) &&
            !($user->hasRole('chief_officer') && ($approvalRequest->chief_officer_approved_at || $approvalRequest->status == 'pending_chief_officer' || $approvalRequest->current_approver_role == 'chief_officer'))
        ) {
            return redirect()->route('requests.show', $approvalRequest)->withErrors('You are not authorized to download this attachment.');
        }

        return Storage::disk('public')->download($approvalRequest->attachment_path);
    }
}
