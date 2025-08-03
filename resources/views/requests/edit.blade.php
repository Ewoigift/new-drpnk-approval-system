<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Request: ') . $request->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('requests.update', $request->id) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH') {{-- Use PATCH method for update --}}

                        <div class="mb-4">
                            <x-input-label for="title" :value="__('Title')" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title', $request->title)" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="description" :value="__('Description')" />
                            {{-- Using a textarea for description --}}
                            <textarea id="description" name="description" rows="5" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>{{ old('description', $request->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="estimated_cost" :value="__('Estimated Cost (KES)')" />
                            <x-text-input id="estimated_cost" class="block mt-1 w-full" type="number" step="0.01" name="estimated_cost" :value="old('estimated_cost', $request->estimated_cost)" required />
                            <x-input-error :messages="$errors->get('estimated_cost')" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="attachment" :value="__('Attachment (PDF, DOCX, JPG, PNG - Max 2MB)')" />
                            <input id="attachment" type="file" name="attachment" class="block mt-1 w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" />
                            <x-input-error :messages="$errors->get('attachment')" class="mt-2" />
                            @if ($request->attachment_path)
                                <p class="text-sm text-gray-600 mt-2">Current attachment: <a href="{{ route('requests.download-attachment', $request->id) }}" class="text-indigo-600 hover:text-indigo-900">Download</a></p>
                            @endif
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Update Request') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
