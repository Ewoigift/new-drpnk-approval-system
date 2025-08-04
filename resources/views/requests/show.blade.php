<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Request Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-xl font-semibold mb-4">{{ $request->title }}</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-6">
                        <div>
                            <p><span class="font-medium">Requester:</span> {{ $request->user->name }}</p>
                            <p><span class="font-medium">Estimated Cost:</span> KES {{ number_format($request->estimated_cost, 2) }}</p>
                            <p><span class="font-medium">Status:</span>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @if ($request->status == 'approved') bg-green-100 text-green-800
                                    @elseif ($request->status == 'rejected') bg-red-100 text-red-800
                                    @elseif (str_contains($request->status, 'pending')) bg-yellow-100 text-yellow-800
                                    @elseif ($request->status == 'sent_back_to_requester') bg-orange-100 text-orange-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                                </span>
                            </p>
                            <p><span class="font-medium">Current Approver:</span> {{ $request->current_approver_role ? ucfirst(str_replace('_', ' ', $request->current_approver_role)) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p><span class="font-medium">Submitted On:</span> {{ $request->created_at->format('M d, Y H:i A') }}</p>
                            @if ($request->attachment_path)
                                <p class="mt-2">
                                    <span class="font-medium">Attachment:</span>
                                    <a href="{{ route('requests.download-attachment', $request) }}" class="text-blue-600 hover:underline">Download File</a>
                                </p>
                            @endif
                            @if ($request->final_outcome)
                                <p class="mt-2"><span class="font-medium">Final Outcome:</span>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        @if ($request->final_outcome == 'approved') bg-green-100 text-green-800
                                        @elseif ($request->final_outcome == 'rejected') bg-red-100 text-red-800
                                        @elseif ($request->final_outcome == 'sent_back') bg-orange-100 text-orange-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst(str_replace('_', ' ', $request->final_outcome)) }}
                                    </span>
                                </p>
                            @endif
                        </div>
                    </div>

                    <h4 class="font-semibold text-lg mt-6 mb-2">Description:</h4>
                    <p class="text-gray-700 mb-6">{{ $request->description }}</p>

                    <h4 class="font-semibold text-lg mt-6 mb-2">Approval History & Comments:</h4>
                    <div class="space-y-4">
                        @if ($request->procurement_approved_at || $request->procurement_comments)
                            <div class="border-l-4 border-gray-400 pl-3">
                                <p class="font-medium">Procurement Officer: @if($request->procurement_approved_at) <span class="text-green-600">Approved</span> on {{ $request->procurement_approved_at->format('M d, Y H:i A') }} @else <span class="text-gray-600">Pending/Commented</span> @endif</p>
                                @if ($request->procurement_comments)<p class="text-gray-700 text-sm italic">"{{ $request->procurement_comments }}"</p>@endif
                            </div>
                        @endif
                        @if ($request->accountant_approved_at || $request->accountant_comments)
                            <div class="border-l-4 border-gray-400 pl-3">
                                <p class="font-medium">Accountant: @if($request->accountant_approved_at) <span class="text-green-600">Approved</span> on {{ $request->accountant_approved_at->format('M d, Y H:i A') }} @else <span class="text-gray-600">Pending/Commented</span> @endif</p>
                                @if ($request->accountant_comments)<p class="text-gray-700 text-sm italic">"{{ $request->accountant_comments }}"</p>@endif
                            </div>
                        @endif
                        @if ($request->coordinator_approved_at || $request->coordinator_comments)
                            <div class="border-l-4 border-gray-400 pl-3">
                                <p class="font-medium">Program Coordinator: @if($request->coordinator_approved_at) <span class="text-green-600">Approved</span> on {{ $request->coordinator_approved_at->format('M d, Y H:i A') }} @else <span class="text-gray-600">Pending/Commented</span> @endif</p>
                                @if ($request->coordinator_comments)<p class="text-gray-700 text-sm italic">"{{ $request->coordinator_comments }}"</p>@endif
                            </div>
                        @endif
                        @if ($request->chief_officer_approved_at || $request->chief_officer_comments)
                            <div class="border-l-4 border-gray-400 pl-3">
                                <p class="font-medium">Chief Officer: @if($request->chief_officer_approved_at) <span class="text-green-600">Approved</span> on {{ $request->chief_officer_approved_at->format('M d, Y H:i A') }} @else <span class="text-gray-600">Pending/Commented</span> @endif</p>
                                @if ($request->chief_officer_comments)<p class="text-gray-700 text-sm italic">"{{ $request->chief_officer_comments }}"</p>@endif
                            </div>
                        @endif
                    </div>

                    {{-- Action buttons for Approvers --}}
                    @php
                        $user = Auth::user();
                        $canApprove = false;
                        $actionPrefix = '';

                        // Determine if the current user can act on this request
                        if ($user->hasRole('procurement') && $request->status == 'pending_procurement') {
                            $canApprove = true;
                            $actionPrefix = 'approve_procurement';
                        } elseif ($user->hasRole('accountant') && $request->status == 'pending_accountant') {
                            $canApprove = true;
                            $actionPrefix = 'approve_accountant';
                        } elseif ($user->hasRole('program_coordinator') && $request->status == 'pending_coordinator') {
                            $canApprove = true;
                            $actionPrefix = 'approve_coordinator';
                        } elseif ($user->hasRole('chief_officer') && $request->status == 'pending_chief_officer') {
                            $canApprove = true;
                            $actionPrefix = 'approve_chief_officer';
                        }
                    @endphp

                    @if ($canApprove)
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="font-semibold text-lg mb-4">Your Action:</h4>
                            <form method="POST" action="{{ route('requests.approve', $request) }}">
                                @csrf

                                <div>
                                    <x-input-label for="comments" :value="__('Comments (Optional)')" />
                                    <x-textarea-input id="comments" class="block mt-1 w-full" name="comments"></x-textarea-input>
                                    <x-input-error :messages="$errors->get('comments')" class="mt-2" />
                                </div>

                                <div class="flex items-center justify-start mt-4 space-x-3">
                                    <x-primary-button name="action" value="{{ $actionPrefix }}">
                                        {{ __('Approve') }}
                                    </x-primary-button>

                                    <x-danger-button name="action" value="{{ str_replace('approve_', 'reject_', $actionPrefix) }}" onclick="return confirm('Are you sure you want to reject this request? This action cannot be undone.')">
                                        {{ __('Reject') }}
                                    </x-danger-button>

                                    {{-- Send Back button --}}
                                    @if ($actionPrefix != 'approve_procurement') {{-- Procurement can send back to requester, others send back to previous stage --}}
                                        <x-secondary-button type="submit" name="action" value="{{ str_replace('approve_', 'send_back_', $actionPrefix) }}">
                                            {{ __('Send Back') }}
                                        </x-secondary-button>
                                    @else
                                        {{-- Procurement specific send back to requester --}}
                                        <x-secondary-button type="submit" name="action" value="send_back_procurement">
                                            {{ __('Send Back to Requester') }}
                                        </x-secondary-button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @elseif (Auth::user()->id === $request->user_id || Auth::user()->hasRole('admin'))
                        {{-- Requester's or Admin's action when request is sent back or if admin wants to edit/delete --}}
                           <div class="mt-8 pt-6 border-t border-gray-200">
                               <h4 class="font-semibold text-lg mb-4">Actions:</h4>
                               <div class="flex items-center justify-start mt-4 space-x-3">
                                   @if (Auth::user()->hasRole('admin') || ($request->status === 'sent_back_to_requester' && Auth::user()->id === $request->user_id))
                                       <a href="{{ route('requests.edit', $request) }}">
                                           <x-secondary-button>
                                               {{ __('Edit Request') }}
                                           </x-secondary-button>
                                       </a>
                                   @endif
                                   @if (Auth::user()->hasRole('admin') || ($request->status === 'sent_back_to_requester' && Auth::user()->id === $request->user_id))
                                       <form method="POST" action="{{ route('requests.destroy', $request) }}" onsubmit="return confirm('Are you sure you want to delete this request? This action cannot be undone.');">
                                           @csrf
                                           @method('DELETE')
                                           <x-danger-button>
                                               {{ __('Delete Request') }}
                                           </x-danger-button>
                                       </form>
                                   @endif
                               </div>
                           </div>
                    @else
                        @if (!Auth::user()->hasRole('requester') && $request->final_outcome == null)
                            <div class="mt-8 pt-6 border-t border-gray-200">
                                <p class="text-gray-600">This request is currently not awaiting your action.</p>
                            </div>
                        @elseif ($request->final_outcome)
                               <div class="mt-8 pt-6 border-t border-gray-200">
                                   <p class="text-gray-600">This request has reached its final outcome: <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $request->final_outcome)) }}</span>.</p>
                               </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('styles')
<style>
    /* Custom styles for textarea-input, if not provided by x-textarea-input */
    textarea {
        border-color: #d2d6dc;
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    textarea:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        outline: none;
    }
</style>
@endpush
