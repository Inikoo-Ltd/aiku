<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Aug 2024 13:24:39 Central Indonesia Time, Bali Office, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Notification from '@/Components/Utils/Notification.vue'
import {usePage} from "@inertiajs/vue3";

</script>

<template>
    <div class="fixed inset-0 bg-slate-100" />

    <div class="isolate relative min-h-full transition-all">

        <!-- Main Content -->
        <main class="h-screen pl-2 md:pl-0 pr-2 ">
            <div class="bg-white shadow-lg rounded h-full overflow-y-auto relative flex flex-col text-gray-700">
                <slot />

            </div>
        </main>
    </div>

    <notifications
        dangerously-set-inner-html
        :max="3"
        width="500"
        classes="custom-style-notification"
        :pauseOnHover="true"
    >
        <template #body="props">
            <Notification :notification="props" />
        </template>
    </notifications>
</template>

<style lang="scss"></style>
