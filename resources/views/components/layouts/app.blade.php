<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="lg:!p-6">
        {{ $slot }}
        <x-toast />
    </flux:main>
</x-layouts.app.sidebar>
