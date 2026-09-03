<script setup lang="ts">
import { library } from '@fortawesome/fontawesome-svg-core'
import { faClipboardListCheck } from '@fal'
import Icon from '@/Components/Icon.vue'
import ShowcaseStats from '@/Components/ShowcaseStats.vue'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(faClipboardListCheck)

defineProps<{
    data: {
        jobPosition: {
            id: number
            slug: string
            code: string
            name: string
            department: string | null
            team: string | null
            scope: string
            number_employees: number
            created_at: string
        }
        stats: {
            label: string
            value: number
            information?: string
        }[]
        employeeStates: {
            state: string
            label: string
            icon: { icon: string; tooltip?: string; class?: string }
            value: number
        }[]
    }
}>()
</script>

<template>
    <div class="px-4 py-4 space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="overflow-hidden rounded-xl border border-gray-300">
                <div class="border-b border-gray-900/5 bg-gray-50 px-6 py-4">
                    <div class="font-semibold capitalize">{{ data.jobPosition.name }}</div>
                    <div class="text-sm text-gray-500">{{ data.jobPosition.code }}</div>
                </div>

                <dl class="divide-y divide-gray-100 px-6 py-2 text-sm/6">
                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans('Scope') }}</dt>
                        <dd class="font-medium capitalize">{{ data.jobPosition.scope?.replace('_', ' ') }}</dd>
                    </div>

                    <div v-if="data.jobPosition.department" class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans('Department') }}</dt>
                        <dd class="font-medium">{{ data.jobPosition.department }}</dd>
                    </div>

                    <div v-if="data.jobPosition.team" class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans('Team') }}</dt>
                        <dd class="font-medium">{{ data.jobPosition.team }}</dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans('Created at') }}</dt>
                        <dd class="font-medium">{{ useFormatTime(data.jobPosition.created_at) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <ShowcaseStats :data="data.stats" />

                <div class="overflow-hidden rounded-xl border border-gray-300">
                    <div class="border-b border-gray-900/5 bg-gray-50 px-6 py-3 font-semibold">
                        {{ ctrans('Employees by state') }}
                    </div>
                    <div class="grid grid-cols-2 divide-x divide-y divide-gray-100 sm:grid-cols-4 sm:divide-y-0">
                        <div v-for="state in data.employeeStates" :key="state.state" class="px-4 py-3">
                            <div class="flex items-center gap-x-2 text-xs font-medium text-gray-400">
                                <Icon :data="state.icon" />
                                {{ state.label }}
                            </div>
                            <div class="text-xl font-bold leading-8 tracking-tight text-indigo-700">
                                {{ state.value }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
