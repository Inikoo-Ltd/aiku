<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { faArrowRight, faDatabase, faExclamationTriangle } from '@fal'
import MiniBar from './MiniBar.vue'

library.add(faArrowRight, faDatabase, faExclamationTriangle)

type ToolStat = {
    tool: string
    calls: number
    errors: number
    avg_ms: number
}

type UserStat = {
    username: string
    sql_access: boolean
    calls: number
    errors: number
}

const props = defineProps<{
    widget?: {
        days: number
        calls: number
        errors: number
        error_rate: number
        users: number
        avg_ms: number
        sql_calls: number
        tool_calls: number
        top_tools: ToolStat[]
        top_users: UserStat[]
    } | null
}>()

const errorTools = computed(() =>
    (props.widget?.top_tools ?? [])
        .filter(tool => tool.errors > 0)
        .sort((a, b) => b.errors - a.errors)
)

const maxToolCalls = computed(() => Math.max(0, ...(props.widget?.top_tools.map(t => t.calls) ?? [])))
const maxToolErrors = computed(() => Math.max(0, ...errorTools.value.map(t => t.errors)))
const maxUserCalls = computed(() => Math.max(0, ...(props.widget?.top_users.map(u => u.calls) ?? [])))
</script>

<template>
    <div class="bg-white rounded-lg p-4 flex flex-col shadow-sm border border-gray-300">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="text-lg font-semibold">
                {{ ctrans("AI insights") }}
                <span v-if="widget" class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(widget.days) }) }}</span>
            </h3>
            <Link
                :href="route('grp.sysadmin.mcp.index')"
                class="text-xs text-indigo-600 hover:underline whitespace-nowrap"
            >
                {{ ctrans("All queries & per-user stats") }}
                <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" />
            </Link>
        </div>

        <template v-if="widget && widget.calls">
            <div class="flex gap-10 mb-4">
                <div>
                    <p class="text-4xl font-bold text-indigo-600">{{ widget.calls.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Queries") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold" :class="widget.error_rate > 10 ? 'text-red-500' : 'text-emerald-600'">{{ widget.error_rate }}%</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Errors") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold text-sky-600">{{ widget.users }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Users") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold text-violet-600">{{ widget.avg_ms.toLocaleString() }}<span class="text-lg text-gray-400">ms</span></p>
                    <p class="text-sm text-gray-600">{{ ctrans("Avg response") }}</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans("Top tools") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tool in widget.top_tools" :key="tool.tool" class="py-1">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0">{{ tool.tool }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ tool.calls }}<span class="text-gray-400 font-normal"> / {{ tool.avg_ms }}ms</span></span>
                            </div>
                            <MiniBar :value="tool.calls" :max="maxToolCalls" color="bg-indigo-400" />
                        </div>
                        <p v-if="!widget.top_tools.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-red-400 mr-1" />{{ ctrans("Tools with errors") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="tool in errorTools" :key="tool.tool" class="py-1">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0">{{ tool.tool }}</span>
                                <span class="shrink-0 tabular-nums font-medium text-red-500">{{ tool.errors }}<span class="text-gray-400 font-normal"> / {{ tool.calls }}</span></span>
                            </div>
                            <MiniBar :value="tool.errors" :max="maxToolErrors" color="bg-red-400" />
                        </div>
                        <p v-if="!errorTools.length" class="py-1 text-gray-400">{{ ctrans("No errors") }} 🎉</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans("Top users") }}</p>
                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="user in widget.top_users"
                            :key="user.username"
                            :href="`${route('grp.sysadmin.mcp.index')}?filter[global]=${encodeURIComponent(user.username)}`"
                            class="block py-1 hover:bg-slate-50"
                        >
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0">
                                    {{ user.username }}
                                    <FontAwesomeIcon v-if="user.sql_access" icon="fal fa-database" class="text-indigo-400" v-tooltip="ctrans('SQL access')" aria-hidden="true" />
                                </span>
                                <span class="shrink-0 tabular-nums font-medium">{{ user.calls }}<span v-if="user.errors" class="text-red-400 font-normal"> / {{ user.errors }} <FontAwesomeIcon icon='fal fa-exclamation-triangle' aria-hidden='true' /></span></span>
                            </div>
                            <MiniBar :value="user.calls" :max="maxUserCalls" color="bg-indigo-400" />
                        </Link>
                        <p v-if="!widget.top_users.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("No AI activity recorded yet") }}</p>
    </div>
</template>
