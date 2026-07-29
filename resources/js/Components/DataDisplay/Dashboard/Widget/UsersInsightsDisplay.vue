<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import { faArrowRight, faDesktop, faMobile, faTabletAlt, faQuestionCircle } from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(faArrowRight, faDesktop, faMobile, faTabletAlt, faQuestionCircle)

type UserStat = {
    username: string
    slug: string
    requests: number
    last_active_at: string
}

type CountStat = {
    requests: number
    device?: string
    browser?: string
}

defineProps<{
    widget?: {
        days: number
        active_users: number
        active_guests: number
        active_today: number
        logins: number
        top_users: UserStat[]
        devices: CountStat[]
        browsers: CountStat[]
    } | null
}>()

const deviceIcon = (device: string) => {
    const key = device.toLowerCase()
    if (key.includes('desktop')) return 'fal fa-desktop'
    if (key.includes('mobile') || key.includes('phone')) return 'fal fa-mobile'
    if (key.includes('tablet')) return 'fal fa-tablet-alt'
    return 'fal fa-question-circle'
}
</script>

<template>
    <div class="bg-white rounded-lg p-4 flex flex-col shadow-sm border border-gray-300">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="text-lg font-semibold">
                {{ ctrans("Users insights") }}
                <span v-if="widget" class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(widget.days) }) }}</span>
            </h3>
            <Link
                :href="route('grp.sysadmin.users.index')"
                class="text-xs text-indigo-600 hover:underline whitespace-nowrap"
            >
                {{ ctrans("All users") }}
                <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" />
            </Link>
        </div>

        <template v-if="widget">
            <div class="flex flex-wrap gap-x-10 gap-y-4 mb-4">
                <div>
                    <p class="text-4xl font-bold">{{ widget.active_users.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Users") }}</p>
                </div>
                <div>
                    <Link :href="route('grp.sysadmin.guests.index')" class="hover:underline">
                        <p class="text-4xl font-bold">{{ widget.active_guests.toLocaleString() }}</p>
                        <p class="text-sm text-gray-600">{{ ctrans("Guests") }}</p>
                    </Link>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ widget.active_today.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Active today") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ widget.logins.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Logins") }}</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Most active users") }}</p>
                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="user in widget.top_users"
                            :key="user.username"
                            :href="route('grp.sysadmin.users.show', [user.slug])"
                            class="flex justify-between gap-2 py-1 hover:bg-slate-50"
                            v-tooltip="useFormatTime(user.last_active_at)"
                        >
                            <span class="text-gray-600 truncate min-w-0">{{ user.username }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ user.requests.toLocaleString() }}</span>
                        </Link>
                        <p v-if="!widget.top_users.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Devices") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="device in widget.devices" :key="device.device" class="flex justify-between gap-2 py-1">
                            <span class="text-gray-600 truncate min-w-0 capitalize">
                                <FontAwesomeIcon :icon="deviceIcon(device.device ?? '')" class="text-gray-400" fixed-width aria-hidden="true" />
                                {{ device.device }}
                            </span>
                            <span class="shrink-0 tabular-nums font-medium">{{ device.requests.toLocaleString() }}</span>
                        </div>
                        <p v-if="!widget.devices.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Browsers") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="browser in widget.browsers" :key="browser.browser" class="flex justify-between gap-2 py-1">
                            <span class="text-gray-600 truncate min-w-0 capitalize">{{ browser.browser }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ browser.requests.toLocaleString() }}</span>
                        </div>
                        <p v-if="!widget.browsers.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("No user activity recorded yet") }}</p>
    </div>
</template>
