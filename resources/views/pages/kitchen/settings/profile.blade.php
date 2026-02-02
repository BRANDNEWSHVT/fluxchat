<?php

use function Laravel\Folio\name;

name('kitchen.settings.profile');

?>

<x-layouts.app :title="__('Settings')">
  <livewire:settings.profile />
</x-layouts.app>
