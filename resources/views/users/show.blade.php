<x-layout>
    <x-slot:title>
        {{ $user->name }}
    </x-slot:title>

    <div class="max-w-3xl mx-auto">
        <div class="card bg-base-100 shadow">
            <div class="card-body space-y-4">
                <div class="flex items-center gap-4">
                    <div class="avatar">
                        <div class="size-16 rounded-full overflow-hidden">
                            <img src="https://avatars.laravel.cloud/{{ urlencode($user->email) }}" alt="{{ $user->name }}'s avatar" />
                        </div>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">{{ $user->name }}</h1>
                        <p class="text-base-content/60">User ID: {{ $user->id }}</p>
                    </div>
                </div>

                <p class="text-sm text-base-content/70">Showing the latest chirps from this profile.</p>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="text-2xl font-semibold mb-4">{{ $chirps->count() }} chirp{{ $chirps->count() === 1 ? '' : 's' }} by {{ $user->name }}</h2>

            <div class="space-y-4">
                @forelse ($chirps as $chirp)
                    <x-chirp :chirp="$chirp" />
                @empty
                    <div class="card bg-base-100 shadow p-6 text-center">
                        <p class="text-base-content/60">No chirps found for this user yet.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $chirps->links() }}
            </div>
        </div>
    </div>
</x-layout>
