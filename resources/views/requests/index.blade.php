<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
    Hello, {{ Auth::user()->name }}!

    @php
        $userRole = Auth::user()->getRoleNames()->first();
    @endphp

    @if ($userRole === 'requester')
        {{ __('Welcome to your dashboard. Here are your submitted requests.') }}
    @elseif ($userRole === 'admin')
        {{ __('Admin Dashboard. Comprehensive overview of all requests.') }}
    @elseif (in_array($userRole, ['procurement', 'accountant', 'program_coordinator', 'chief_officer']))
        {{ __('Requests awaiting your action.') }}
    @else
        {{ __('Your Dashboard') }}
    @endif
</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
    @php
        $userRole = Auth::user()->getRoleNames()->first();
        $overviewTitle = 'Requests Overview'; // Default title
        if ($userRole === 'requester') {
            $overviewTitle = 'My Submitted Requests';
        } elseif (in_array($userRole, ['procurement', 'accountant', 'program_coordinator', 'chief_officer'])) {
            $overviewTitle = 'Requests Awaiting My Approval'; // Or 'Pending Your Action'
        } elseif ($userRole === 'admin') {
            $overviewTitle = 'All Requests in the System';
        }
    @endphp
    {{ $overviewTitle }}
</h3>
                        @if (Auth::user()->hasRole('requester'))
                            <a href="{{ route('requests.create') }}">
                                <x-primary-button>
                                    {{ __('Create New Request') }}
                                </x-primary-button>
                            </a>
                        @endif
                    </div>

                    @if ($requests->isEmpty())
                        <p class="text-gray-600">No requests found.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Title
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Requester
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estimated Cost
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Current Approver
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Submitted On
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($requests as $request)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $request->title }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $request->user->name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                KES {{ number_format($request->estimated_cost, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    @if ($request->status == 'approved') bg-green-100 text-green-800
                                                    @elseif ($request->status == 'rejected') bg-red-100 text-red-800
                                                    @elseif (str_contains($request->status, 'pending')) bg-yellow-100 text-yellow-800
                                                    @elseif ($request->status == 'sent_back_to_requester') bg-orange-100 text-orange-800
                                                    @else bg-gray-100 text-gray-800 @endif">
                                                    {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $request->current_approver_role ? ucfirst(str_replace('_', ' ', $request->current_approver_role)) : 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $request->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center space-x-2">
                                                    <a href="{{ route('requests.show', $request) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                                    {{-- Edit and Delete buttons are now moved to the show.blade.php for detailed actions --}}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
