<script setup lang="ts">
import { trans } from "laravel-vue-i18n"

defineProps<{
    noteToPickers?: string | null
    noteToPackers?: string | null
}>()
</script>

<template>
    <div v-if="noteToPickers || noteToPackers" class="mt-1 space-y-0.5">
        <div v-if="noteToPickers" class="text-xs px-1.5 py-0.5 rounded-sm bg-amber-100 text-amber-900 border border-amber-300 whitespace-pre-wrap">
            <span class="font-medium">{{ trans("Pickers") }}:</span> {{ noteToPickers }}
        </div>
        <div v-if="noteToPackers" class="text-xs px-1.5 py-0.5 rounded-sm bg-sky-100 text-sky-900 border border-sky-300 whitespace-pre-wrap">
            <span class="font-medium">{{ trans("Packers") }}:</span> {{ noteToPackers }}
        </div>
    </div>
</template>
