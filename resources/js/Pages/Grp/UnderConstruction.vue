<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 12 May 2026 08:15:14 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

</script>

<template>

    <main class="grid min-h-full place-items-center bg-white px-6 py-24 sm:py-32 lg:px-8">
        <div class="text-center">
            <p class="text-6xl">🏗️</p>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900 sm:text-5xl">{{ trans("Under construction") }}</h1>
            <p class="mt-6 text-base leading-7 text-gray-600">We are working on it. 😉</p>
        </div>
    </main>
</template>