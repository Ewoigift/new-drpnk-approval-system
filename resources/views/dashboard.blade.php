<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}

                    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @if (Auth::user()->hasRole('requester'))
                            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-md">
                                <div class="flex items-center">
                                    <svg class="h-6 w-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                    <p class="font-bold">My Submitted Requests</p>
                                </div>
                                <p class="text-sm mt-1">You have submitted **{{ $submittedRequestsCount }}** requests.</p>
                                <a href="{{ route('requests.index', ['filter_by_user' => Auth::id()]) }}" class="text-blue-700 hover:text-blue-900 text-xs mt-2 inline-block">View all submitted requests</a>
                            </div>
                        @endif

                        @if (Auth::user()->hasAnyRole(['procurement', 'accountant', 'program_coordinator', 'chief_officer']))
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md">
                                <div class="flex items-center">
                                    <svg class="h-6 w-6 text-yellow-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="font-bold">Requests Pending Your Approval</p>
                                </div>
                                <p class="text-sm mt-1">You have **{{ $pendingApprovalsCount }}** requests currently awaiting your approval.</p>
                                <a href="{{ route('requests.index') }}" class="text-yellow-700 hover:text-yellow-900 text-xs mt-2 inline-block">View pending approvals</a>
                            </div>
                        @endif

                        @if (Auth::user()->hasRole('admin'))
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md">
                                <div class="flex items-center">
                                    <svg class="h-6 w-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="font-bold">Total Requests</p>
                                </div>
                                <p class="text-sm mt-1">**{{ $totalRequestsCount }}** total requests in the system.</p>
                                <a href="{{ route('requests.index') }}" class="text-green-700 hover:text-green-900 text-xs mt-2 inline-block">View all requests</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
