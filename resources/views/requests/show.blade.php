<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Request Details: {{ $request->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-semibold mb-4">{{ $request->title }}</h3>

                    <div class="mb-4">
                        <p><strong>Description:</strong> {{ $request->description }}</p>
                        <p><strong>Estimated Cost:</strong> KES {{ number_format($request->estimated_cost, 2) }}</p>
                        <p><strong>Requested By:</strong> {{ $request->user->name }}</p>
                        <p><strong>Status:</strong>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                @if ($request->status == 'approved') bg-green-100 text-green-800
                                @elseif ($request->status == 'rejected') bg-red-100 text-red-800
                                @elseif (str_contains($request->status, 'pending')) bg-yellow-100 text-yellow-800
                                @elseif ($request->status == 'sent_back_to_requester') bg-orange-100 text-orange-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                            </span>
                        </p>
                        <p><strong>Current Approver:</strong> {{ $request->current_approver_role ? ucfirst(str_replace('_', ' ', $request->current_approver_role)) : 'N/A' }}</p>
                        <p><strong>Submitted On:</strong> {{ $request->created_at->format('M d, Y H:i A') }}</p>
                    </div>

                    @if($request->attachment_path)
                        <div class="mb-4">
                            <p><strong>Attachment:</strong>
                                <a href="{{ route('requests.download-attachment', $request->id) }}" class="text-indigo-600 hover:text-indigo-900 ml-2">Download Attachment</a>
                            </p>
                        </div>
                    @endif

                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <h4 class="text-md font-semibold mb-3">Approval History:</h4>
                        <ul class="list-disc ml-5">
                            {{-- Procurement Stage --}}
                            <li>
                                <strong>Procurement:</strong>
                                @if ($request->procurement_approved_at)
                                    Approved on {{ $request->procurement_approved_at->format('M d, Y H:i A') }}
                                @elseif ($request->final_outcome == 'rejected' && $request->current_approver_role === 'procurement')
                                    Rejected
                                @elseif ($request->final_outcome == 'sent_back' && $request->current_approver_role === 'procurement')
                                    Sent Back
                                @else
                                    Pending
                                @endif
                                @if ($request->procurement_comments)
                                    <p class="text-sm text-gray-600 ml-3">Comments: {{ $request->procurement_comments }}</p>
                                @endif
                            </li>

                            {{-- Accountant Stage --}}
                            <li>
                                <strong>Accountant:</strong>
                                @if ($request->accountant_approved_at)
                                    Approved on {{ $request->accountant_approved_at->format('M d, Y H:i A') }}
                                @elseif ($request->final_outcome == 'rejected' && $request->current_approver_role === 'accountant')
                                    Rejected
                                @elseif ($request->final_outcome == 'sent_back' && $request->current_approver_role === 'accountant')
                                    Sent Back
                                @else
                                    Pending
                                @endif
                                @if ($request->accountant_comments)
                                    <p class="text-sm text-gray-600 ml-3">Comments: {{ $request->accountant_comments }}</p>
                                @endif
                            </li>

                            {{-- Program Coordinator Stage --}}
                            <li>
                                <strong>Program Coordinator:</strong>
                                @if ($request->coordinator_approved_at)
                                    Approved on {{ $request->coordinator_approved_at->format('M d, Y H:i A') }}
                                @elseif ($request->final_outcome == 'rejected' && $request->current_approver_role === 'program_coordinator')
                                    Rejected
                                @elseif ($request->final_outcome == 'sent_back' && $request->current_approver_role === 'program_coordinator')
                                    Sent Back
                                @else
                                    Pending
                                @endif
                                @if ($request->coordinator_comments)
                                    <p class="text-sm text-gray-600 ml-3">Comments: {{ $request->coordinator_comments }}</p>
                                @endif
                            </li>

                            {{-- Chief Officer Stage --}}
                            <li>
                                <strong>Chief Officer:</strong>
                                @if ($request->chief_officer_approved_at)
                                    Approved on {{ $request->chief_officer_approved_at->format('M d, Y H:i A') }}
                                @elseif ($request->final_outcome == 'rejected' && $request->current_approver_role === 'chief_officer')
                                    Rejected
                                @elseif ($request->final_outcome == 'sent_back' && $request->current_approver_role === 'chief_officer')
                                    Sent Back
                                @else
                                    Pending
                                @endif
                                @if ($request->chief_officer_comments)
                                    <p class="text-sm text-gray-600 ml-3">Comments: {{ $request->chief_officer_comments }}</p>
                                @endif
                            </li>

                            @if ($request->final_outcome)
                                <li>
                                    <strong>Final Outcome:</strong>
                                    <span class="font-semibold
                                        @if ($request->final_outcome == 'approved') text-green-700
                                        @elseif ($request->final_outcome == 'rejected') text-red-700
                                        @elseif ($request->final_outcome == 'sent_back') text-orange-700
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $request->final_outcome)) }}
                                    </span>
                                </li>
                            @endif
                        </ul>
                    </div>

                    @php
                        $user = Auth::user();
                        $currentRole = $user->getRoleNames()->first();
                        $isRequester = ($user->id === $request->user_id);
                        $canApprove = false;
                        $canEdit = false;
                        $canDelete = false;

                        // Logic for who can approve
                        if ($user->hasRole('admin')) {
                            $canApprove = true; // Admin can approve any
                        } elseif (
                            ($user->hasRole('procurement') && $request->status === 'pending_procurement') ||
                            ($user->hasRole('accountant') && $request->status === 'pending_accountant') ||
                            ($user->hasRole('program_coordinator') && $request->status === 'pending_coordinator') ||
                            ($user->hasRole('chief_officer') && $request->status === 'pending_chief_officer')
                        ) {
                            $canApprove = true;
                        }

                        // Logic for who can edit
                        if ($isRequester && ($request->status === 'pending_procurement' || $request->status === 'sent_back_to_requester')) {
                            $canEdit = true;
                        }
                        if ($user->hasRole('admin')) {
                            $canEdit = true; // Admin can edit any
                        }

                        // Logic for who can delete
                        if ($isRequester && ($request->status === 'pending_procurement' || $request->status === 'sent_back_to_requester')) {
                            $canDelete = true;
                        }
                        if ($user->hasRole('admin')) {
                            $canDelete = true; // Admin can delete any
                        }
                    @endphp

                    <div class="mt-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        @if ($canEdit)
                            <a href="{{ route('requests.edit', $request->id) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Edit Request
                            </a>
                        @endif

                        @if ($canDelete)
                            <form action="{{ route('requests.destroy', $request->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this request? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <x-danger-button type="submit">
                                    Delete Request
                                </x-danger-button>
                            </form>
                        @endif

                        @if ($canApprove)
                            <div class="w-full">
                                <form action="{{ route('requests.approve', $request->id) }}" method="POST" class="w-full">
                                    @csrf
                                    <div class="mb-4">
                                        <x-input-label for="comments" :value="__('Comments (Optional)')" />
                                        <x-text-input id="comments" name="comments" type="text" class="mt-1 block w-full" />
                                        <x-input-error class="mt-2" :messages="$errors->get('comments')" />
                                    </div>

                                    <div class="flex items-center space-x-4">
                                        {{-- APPROVE BUTTON --}}
                                        <x-primary-button type="submit" name="action" value="approve">
                                            {{ __('Approve') }}
                                        </x-primary-button>

                                        {{-- REJECT BUTTON --}}
                                        <x-danger-button type="submit" name="action" value="reject" onclick="return confirm('Are you sure you want to REJECT this request? This action will set the final outcome to rejected.');">
                                            {{ __('Reject') }}
                                        </x-danger-button>

                                        {{-- SEND BACK TO REQUESTER BUTTON --}}
                                        <x-secondary-button type="submit" name="action" value="send_back_to_requester" onclick="return confirm('Are you sure you want to SEND BACK this request to the requester for modifications?');">
                                            {{ __('Send Back to Requester') }}
                                        </x-secondary-button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
