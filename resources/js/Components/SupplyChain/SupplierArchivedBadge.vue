<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-26 11:36
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArchive } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    archivedAt?: string | null
}>()
</script>

<template>
    <span v-tooltip="ctrans('This supplier is archived in every organisation. Unarchive it to order from it again.')" class="inline-flex items-center gap-1 whitespace-nowrap rounded border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
        <FontAwesomeIcon :icon="faArchive" fixed-width aria-hidden="true" />
        {{ archivedAt ? ctrans("Archived :date", { date: useFormatTime(archivedAt) }) : ctrans("Archived") }}
    </span>
</template>
