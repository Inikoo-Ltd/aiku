<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

export interface CiStep { number: number, name: string, status: string, conclusion: string | null, started_at: string | null, completed_at: string | null }
export interface CiJob { name: string, status: string, conclusion: string | null, started_at: string | null, completed_at: string | null, html_url: string | null, steps: CiStep[] }
export interface DeployTask { task: string, state: "start" | "done" | "failed", started_at: string, finished_at: string | null, hosts: string[] }
export interface CiRunSummary {
    github_run_id: number
    workflow: string | null
    branch: string | null
    head_sha: string | null
    head_message: string | null
    actor: string | null
    status: string | null
    conclusion: string | null
    html_url: string | null
    started_at: string | null
    completed_at: string | null
    test_counts: { tests: number, failed: number, skipped: number } | null
}
export interface CiRunDetail extends CiRunSummary {
    jobs: CiJob[]
    deploy_tasks: DeployTask[]
    deploy_total: number | null
    deploy_done: number
    failed_tests: { test: string, message: string | null }[]
}

const props = defineProps<{ title: string, run: CiRunDetail | null, recent: CiRunSummary[], usualSeconds?: number | null }>()

const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 1000)))
onBeforeUnmount(() => clearInterval(clock))

const isRunning = (run: CiRunSummary | null) => !!run && run.status !== "completed" && !run.conclusion

const seconds = (from: string | null, to: string | null) => from ? Math.max(0, Math.round(((to ? new Date(to).getTime() : now.value) - new Date(from).getTime()) / 1000)) : null

const formatDuration = (total: number | null) => total === null ? "-" : total >= 60 ? `${Math.floor(total / 60)}m ${String(total % 60).padStart(2, "0")}s` : `${total}s`

const outcome = (status: string | null, conclusion: string | null) => {
    if (conclusion === "success") return { label: ctrans("Passed"), dot: "bg-emerald-500", text: "text-emerald-700" }
    if (conclusion === "failure" || conclusion === "timed_out") return { label: ctrans("Failed"), dot: "bg-red-500", text: "text-red-700" }
    if (conclusion === "cancelled" || conclusion === "skipped") return { label: ctrans("Cancelled"), dot: "bg-gray-400", text: "text-gray-600" }
    if (status === "queued" || status === "waiting" || status === "requested" || status === "pending") return { label: ctrans("Queued"), dot: "bg-amber-400", text: "text-amber-700" }
    if (status) return { label: ctrans("Running"), dot: "bg-sky-500 animate-pulse", text: "text-sky-700" }
    return { label: "-", dot: "bg-gray-300", text: "text-gray-500" }
}

const ago = (iso: string | null) => {
    if (!iso) return ""
    const diff = Math.max(0, Math.round((now.value - new Date(iso).getTime()) / 1000))
    if (diff < 60) return ctrans("just now")
    if (diff < 3600) return ctrans(":count min ago", { count: String(Math.floor(diff / 60)) })
    if (diff < 86400) return ctrans(":count h ago", { count: String(Math.floor(diff / 3600)) })
    return ctrans(":count d ago", { count: String(Math.floor(diff / 86400)) })
}

const when = (iso: string | null) => iso ? useFormatTime(iso, { formatTime: "short-datetime" }) : ""

const elapsed = computed(() => props.run ? seconds(props.run.started_at, props.run.completed_at) : null)

const eta = computed(() => {
    const run = props.run
    if (!run || !isRunning(run) || !run.started_at || !props.usualSeconds || elapsed.value === null) return null
    const remaining = props.usualSeconds - elapsed.value
    if (remaining < 0) return { late: true, text: ctrans(":duration longer than usual", { duration: formatDuration(-remaining) }) }
    const finishAt = new Date(new Date(run.started_at).getTime() + props.usualSeconds * 1000)
    return { late: false, text: `${ctrans("about :duration left", { duration: formatDuration(remaining) })} · ${ctrans("done around :time", { time: finishAt.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) })}` }
})

const steps = computed(() => props.run?.jobs.flatMap(job => job.steps.map(step => ({ ...step, job: job.name }))) ?? [])

const currentTasks = computed(() => props.run?.deploy_tasks.filter(task => task.state === "start") ?? [])

const progress = computed(() => {
    const run = props.run
    if (!run) return null
    if (run.deploy_total) return Math.min(100, Math.round((run.deploy_done / run.deploy_total) * 100))
    if (!steps.value.length) return null
    return Math.round((steps.value.filter(step => step.status === "completed").length / steps.value.length) * 100)
})

const stepIcon = (status: string, conclusion: string | null) =>
    conclusion === "success" ? "✓" : conclusion === "failure" ? "✗" : conclusion === "skipped" || conclusion === "cancelled" ? "–" : status === "in_progress" ? "●" : "○"

const stepColour = (status: string, conclusion: string | null) =>
    conclusion === "success" ? "text-emerald-600" : conclusion === "failure" ? "text-red-600" : status === "in_progress" ? "text-sky-600 animate-pulse" : "text-gray-300"

const showAllTasks = ref(false)
const showSteps = ref(false)

const formatCount = (count: number) => count.toLocaleString()

const hasCounts = computed(() => props.recent.some(previous => previous.test_counts))
</script>

<template>
    <div class="rounded-lg border border-gray-200 p-4">
        <div class="flex items-baseline justify-between">
            <span class="text-sm font-medium">{{ title }}</span>
            <span v-if="run" class="flex items-center gap-1 text-xs" :class="outcome(run.status, run.conclusion).text">
                <span class="h-1.5 w-1.5 rounded-full" :class="outcome(run.status, run.conclusion).dot" />{{ outcome(run.status, run.conclusion).label }}
            </span>
        </div>

        <div v-if="!run" class="mt-3 text-xs text-gray-500">{{ ctrans("No runs recorded yet") }}</div>
        <template v-else>
            <a v-if="run.html_url" :href="run.html_url" target="_blank" rel="noopener" class="mt-2 block truncate text-sm hover:underline">{{ run.head_message ?? run.head_sha }}</a>
            <div class="mt-1 flex flex-wrap gap-x-3 text-xs text-gray-500 tabular-nums">
                <span v-if="run.head_sha" class="font-mono">{{ run.head_sha }}</span>
                <span v-if="run.actor">{{ run.actor }}</span>
                <span v-if="run.started_at" :title="when(run.started_at)">{{ when(run.started_at) }} · {{ isRunning(run) ? ago(run.started_at) : ctrans("finished") + " " + ago(run.completed_at ?? run.started_at) }}</span>
                <span v-if="isRunning(run)" class="text-gray-700">{{ ctrans("running") }} {{ formatDuration(elapsed) }}</span>
                <span v-else>{{ ctrans("took") }} {{ formatDuration(elapsed) }}<template v-if="usualSeconds"> / {{ ctrans("usually") }} {{ formatDuration(usualSeconds) }}</template></span>
                <span v-if="eta" :class="eta.late ? 'text-amber-600' : 'text-sky-700'">{{ eta.text }}</span>
            </div>

            <div v-if="run.test_counts" class="mt-2 flex flex-wrap gap-x-3 text-sm tabular-nums">
                <span class="text-emerald-700">{{ formatCount(run.test_counts.tests - run.test_counts.failed - run.test_counts.skipped) }} {{ ctrans("passed") }}</span>
                <span v-if="run.test_counts.failed" class="font-medium text-red-700">{{ formatCount(run.test_counts.failed) }} {{ ctrans("failed") }}</span>
                <span v-if="run.test_counts.skipped" class="text-gray-500">{{ formatCount(run.test_counts.skipped) }} {{ ctrans("skipped") }}</span>
                <span class="text-gray-400">{{ ctrans("of") }} {{ formatCount(run.test_counts.tests) }}</span>
            </div>
            <ul v-if="run.failed_tests.length" class="mt-2 space-y-1 rounded bg-red-50 p-2 text-xs">
                <li v-for="failed in run.failed_tests" :key="failed.test">
                    <div class="font-medium text-red-800">✗ {{ failed.test }}</div>
                    <div v-if="failed.message" class="whitespace-pre-line break-words font-mono text-red-700">{{ failed.message }}</div>
                </li>
            </ul>

            <div v-if="progress !== null" class="mt-3">
                <div class="h-1.5 rounded bg-gray-100">
                    <div class="h-1.5 rounded transition-all" :class="run.conclusion === 'failure' ? 'bg-red-500' : run.conclusion === 'success' ? 'bg-emerald-500' : 'bg-sky-500'" :style="{ width: `${progress}%` }" />
                </div>
                <div v-if="run.deploy_total" class="mt-1 flex justify-between text-xs text-gray-500 tabular-nums">
                    <span class="truncate">
                        <template v-if="currentTasks.length">{{ currentTasks.map(task => task.task).join(", ") }}</template>
                        <template v-else-if="isRunning(run)">{{ ctrans("Between tasks") }}</template>
                    </span>
                    <span>{{ run.deploy_done }}/{{ run.deploy_total }} {{ ctrans("tasks") }}</span>
                </div>
            </div>

            <button v-if="steps.length && run.failed_tests.length" type="button" class="mt-3 text-xs text-indigo-600 hover:underline" @click.prevent="showSteps = !showSteps">
                {{ showSteps ? ctrans("Hide CI steps") : ctrans("Show CI steps") }}
            </button>
            <table v-if="steps.length && (showSteps || !run.failed_tests.length)" class="mt-3 w-full table-fixed text-xs tabular-nums">
                <colgroup><col class="w-4"><col><col class="w-16"></colgroup>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="step in steps" :key="`${step.job}-${step.number}`">
                        <td class="w-4 py-1" :class="stepColour(step.status, step.conclusion)">{{ stepIcon(step.status, step.conclusion) }}</td>
                        <td class="truncate py-1" :class="step.status === 'in_progress' ? 'font-medium' : 'text-gray-600'">{{ step.name }}</td>
                        <td class="w-16 py-1 text-right text-gray-500">{{ step.started_at ? formatDuration(seconds(step.started_at, step.completed_at)) : "" }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="run.deploy_tasks.length" class="mt-3">
                <button type="button" class="text-xs text-indigo-600 hover:underline" @click.prevent="showAllTasks = !showAllTasks">
                    {{ showAllTasks ? ctrans("Hide deploy tasks") : ctrans("Show deploy tasks") }}
                </button>
                <table v-if="showAllTasks" class="mt-1 w-full table-fixed text-xs tabular-nums">
                    <colgroup><col class="w-4"><col><col class="w-28"><col class="w-16"></colgroup>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="task in run.deploy_tasks" :key="task.task">
                            <td class="w-4 py-1" :class="task.state === 'done' ? 'text-emerald-600' : task.state === 'failed' ? 'text-red-600' : 'animate-pulse text-sky-600'">
                                {{ task.state === "done" ? "✓" : task.state === "failed" ? "✗" : "●" }}
                            </td>
                            <td class="truncate py-1 text-gray-600" :title="task.task">{{ task.task }}</td>
                            <td class="w-28 truncate py-1 text-right text-gray-400">{{ task.hosts.join(", ") }}</td>
                            <td class="w-16 py-1 text-right text-gray-500">{{ formatDuration(seconds(task.started_at, task.finished_at)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <table v-if="recent.length" class="mt-3 w-full table-fixed text-xs tabular-nums">
            <colgroup><col class="w-4"><col><col v-if="hasCounts" class="w-28"><col class="w-24"><col class="w-16"></colgroup>
            <thead class="text-gray-400">
                <tr><th colspan="5" class="pb-1 text-left font-normal">{{ ctrans("Previous") }}</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="previous in recent" :key="previous.github_run_id">
                    <td class="w-4 py-1"><span class="inline-block h-1.5 w-1.5 rounded-full" :class="outcome(previous.status, previous.conclusion).dot" /></td>
                    <td class="truncate py-1 text-gray-600">
                        <a v-if="previous.html_url" :href="previous.html_url" target="_blank" rel="noopener" class="hover:underline">{{ previous.head_message ?? previous.head_sha }}</a>
                    </td>
                    <td v-if="hasCounts" class="py-1 text-right">
                        <template v-if="previous.test_counts">
                            <span v-if="previous.test_counts.failed" class="text-red-700">{{ formatCount(previous.test_counts.failed) }} {{ ctrans("failed") }}</span>
                            <span v-else class="text-emerald-700">{{ formatCount(previous.test_counts.tests) }} ✓</span>
                        </template>
                    </td>
                    <td class="py-1 text-right text-gray-500" :title="when(previous.started_at)">{{ ago(previous.started_at) }}</td>
                    <td class="py-1 text-right text-gray-500">{{ formatDuration(seconds(previous.started_at, previous.completed_at)) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
