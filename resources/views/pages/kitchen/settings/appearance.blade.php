<?php

use function Laravel\Folio\name;

name('kitchen.settings.appearance');

?>

<x-layouts.app :title="__('Settings')">
  <livewire:settings.appearance />
</x-layouts.app>
