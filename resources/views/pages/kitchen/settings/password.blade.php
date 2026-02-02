<?php

use function Laravel\Folio\name;

name('kitchen.settings.password');

?>

<x-layouts.app :title="__('Settings')">
  <livewire:settings.password />
</x-layouts.app>
