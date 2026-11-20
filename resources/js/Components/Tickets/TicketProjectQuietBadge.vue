<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 20 Nov 2026 12:45:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSnooze } from "@fal"

library.add(faSnooze)

defineProps<{ days: number | null | undefined }>()
</script>

<template>
    <span
        v-if="days != null && days >= 5"
        v-tooltip="ctrans('Quiet: no updates, tickets or tasks have moved on this project for :days days', { days })"
        class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs tabular-nums"
        :class="days >= 21 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'"
    >
        <FontAwesomeIcon icon="fal fa-snooze" fixed-width aria-hidden="true" />{{ days }}d
    </span>
</template>
