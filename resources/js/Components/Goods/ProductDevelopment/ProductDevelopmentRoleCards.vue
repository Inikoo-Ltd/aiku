<!--
  - Author Louis Perez
  - Created on 16-10-2026-00h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import ProductDevelopmentProgressBar from "@/Components/Goods/ProductDevelopment/ProductDevelopmentProgressBar.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBellOn } from "@fal"
library.add(faBellOn)

defineProps<{
    roles: {
        role: string
        label: string
        done: number
        total: number
        missing: number
        starts_after_gate: boolean
        reminded?: { at: string, by: string } | null
    }[]
}>()

const remindedTooltip = (reminded: { at: string, by: string }) => ctrans("Reminded :date by :user", { date: useFormatTime(reminded.at, { formatTime: "d MMM, HH:mm" }), user: reminded.by })
</script>

<template>
    <div class="grid grid-cols-2 gap-3 px-4 py-4 sm:grid-cols-3 md:px-6" :class="roles.length > 5 ? 'lg:grid-cols-6' : 'lg:grid-cols-5'">
        <div v-for="role in roles" :key="role.role" class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium text-gray-800">{{ role.label }}</span>
                <span class="text-xs tabular-nums text-gray-500">{{ role.total ? role.done + "/" + role.total : "—" }}</span>
            </div>
            <ProductDevelopmentProgressBar :done="role.done" :total="role.total" class="mt-2" />
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span v-if="role.starts_after_gate" class="text-gray-500">{{ ctrans("Starts after the gate") }}</span>
                <span v-else-if="!role.total" class="text-gray-400">{{ ctrans("Nothing to fill") }}</span>
                <span v-else-if="role.missing" class="text-amber-700">{{ ctrans(":count missing", { count: String(role.missing) }) }}</span>
                <span v-else class="text-green-700">{{ ctrans("All done") }}</span>
                <span
                    v-if="role.reminded && role.missing"
                    v-tooltip="remindedTooltip(role.reminded)"
                    class="ml-auto inline-flex items-center gap-1 rounded-full bg-violet-50 px-1.5 py-0.5 text-violet-700"
                    :aria-label="remindedTooltip(role.reminded)"
                >
                    <FontAwesomeIcon icon="fal fa-bell-on" fixed-width aria-hidden="true" />
                    {{ useFormatTime(role.reminded.at, { formatTime: "d MMM" }) }}
                </span>
            </div>
        </div>
    </div>
</template>
