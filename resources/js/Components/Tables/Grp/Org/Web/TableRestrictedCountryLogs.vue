<!--
  - Author: stewicca <wiccaalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime";

const props = defineProps<{
    data: object
    tab?: string
}>()

const flagUrl = (code: string) => `/flags/${code.toLowerCase()}.png`
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(country)="{ item }">
            <div class="flex items-center gap-2">
                <img
                    :src="flagUrl(item.country)"
                    :alt="item.country"
                    class="h-4 w-auto rounded-[2px] shrink-0"
                    loading="lazy"
                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
                <span>{{ item.country }}</span>
            </div>
        </template>
        <template #cell(status)="{ item }">
            <span
                class="relative flex h-3 w-3"
                :title="item.was_blocked ? trans('Blocked') : trans('Allowed')"
            >
                <span
                    v-if="item.was_blocked"
                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
                />
                <span
                    class="relative inline-flex h-3 w-3 rounded-full"
                    :class="item.was_blocked ? 'bg-red-500' : 'bg-green-500'"
                />
            </span>
        </template>
        <template  #cell(last_request_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.last_request_at, { formatTime: "dd MMM yyyy, HH:mm", timeZone: 'UTC', keepTimezone: true }) }} UTC</span>
        </template>
    </Table>
</template>
