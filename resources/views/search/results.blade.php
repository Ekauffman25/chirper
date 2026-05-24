<x-layout>
    <x-slot:title>
        Search results for "{{ $query }}"
    </x-slot:title>

    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-3xl font-bold">Search</h1>
            <p class="text-base-content/60 mt-2">Showing matches for "{{ $query ?: 'nothing yet' }}".</p>
        </div>

        @if ($query === '')
            <div class="card bg-base-100 shadow p-6">
                <p class="text-base-content/70">Type a name, user ID, or keyword from a chirp to begin searching.</p>
            </div>
        @endif

        @if ($users->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-xl font-semibold">Users</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($users as $resultUser)
                        <a href="{{ route('users.show', $resultUser) }}" class="card bg-base-100 shadow p-4 hover:bg-base-200 transition">
                            <div class="flex items-center gap-3">
                                <div class="avatar">
                                    <div class="size-12 rounded-full overflow-hidden">
                                        <img src="https://avatars.laravel.cloud/{{ urlencode($resultUser->email) }}" alt="{{ $resultUser->name }}" />
                                    </div>
                                </div>
                                <div>
                                    <div class="font-semibold">{{ $resultUser->name }}</div>
                                    <div class="text-sm text-base-content/60">User ID: {{ $resultUser->id }}</div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($chirps->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-xl font-semibold">Chirps</h2>
                <div class="space-y-4">
                    @foreach ($chirps as $chirp)
                        <x-chirp :chirp="$chirp" />
                    @endforeach
                </div>
            </div>
        @endif

        @if ($query !== '' && $users->isEmpty() && $chirps->isEmpty())
            <div class="card bg-base-100 shadow p-6 text-center">
                <p class="text-base-content/70">We couldn't find any users or chirps matching "{{ $query }}".</p>
            </div>
        @endif
    </div>
</x-layout>
