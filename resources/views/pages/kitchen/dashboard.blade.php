<?php

use function Laravel\Folio\name;

name('kitchen.dashboard');
?>

<x-layouts.app :title="__('Dashboard')">
    <div class="h-full w-full">
        <livewire:dashboard />
    </div>
</x-layouts.app>
