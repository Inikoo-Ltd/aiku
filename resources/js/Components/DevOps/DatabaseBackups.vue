<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 17 Oct 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"

export interface DatabaseBackup {
    repo: number
    repo_name: string
    label: string
    type: "full" | "diff" | "incr"
    started_at: string
    finished_at: string
    duration_seconds: number
    size: number | null
    repo_size: number | null
    repo_delta: number | null
}

export interface DatabaseBackupsReport {
    archive_mode: boolean
    last_archived_at: string | null
    last_failed_at: string | null
    archived_count: number
    failed_count: number
    reported_at: string | null
    backup_running: boolean
    backups: DatabaseBackup[]
    restore_from: string | null
    restore_to: string | null
    archive_issue: string | null
    backup_issue: string | null
}

const props = defineProps<{ report: DatabaseBackupsReport }>()

const healthy = { dot: "bg-emerald-500", text: "text-emerald-700", label: ctrans("Healthy") }
const broken = { dot: "bg-red-500", text: "text-red-700", label: ctrans("Broken") }

const archiveStatus = computed(() => (props.report.archive_issue ? broken : healthy))
const backupStatus = computed(() => (props.report.backup_issue ? broken : healthy))

const when = (iso: string | null) => (iso ? useFormatTime(iso, { formatTime: "short-datetime" }) : "-")
const ago = (iso: string | null) => (iso ? useRangeFromNow(iso, { addSuffix: true }) : "-")

const formatSize = (bytes: number | null) => {
    if (bytes === null) {
        return "-"
    }
    const gigabytes = bytes / 1024 ** 3

    return gigabytes >= 1 ? `${gigabytes.toFixed(1)} GB` : `${(bytes / 1024 ** 2).toFixed(0)} MB`
}

const duration = (seconds: number) => (seconds >= 3600 ? `${Math.floor(seconds / 3600)} h ${Math.round((seconds % 3600) / 60)} min` : `${Math.max(1, Math.round(seconds / 60))} min`)

const types = { full: ctrans("Full"), diff: ctrans("Differential"), incr: ctrans("Incremental") }

const cardClass = (issue: string | null) => (issue ? "border-red-300 bg-red-50" : "border-gray-200")
</script>

<template>
    <section>
        <h4 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Database backups (pgBackRest)") }}</h4>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border p-4" :class="cardClass(report.archive_issue)">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-medium">{{ ctrans("WAL archiving from boro") }}</span>
                    <span class="flex shrink-0 items-center gap-1 text-xs" :class="archiveStatus.text">
                        <span class="h-1.5 w-1.5 rounded-full" :class="archiveStatus.dot" />{{ archiveStatus.label }}
                    </span>
                </div>
                <div class="mt-0.5 text-xs text-gray-500">{{ ctrans("Every change, sent continuously") }}</div>
                <p v-if="report.archive_issue" class="mt-3 text-sm text-red-700">{{ report.archive_issue.replace(/`/g, "") }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded bg-white/60 p-3 ring-1 ring-gray-100">
                        <dt class="text-xs text-gray-500">{{ ctrans("Last sent") }}</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums">{{ ago(report.last_archived_at) }}</dd>
                        <dd class="text-xs text-gray-400">{{ when(report.last_archived_at) }}</dd>
                    </div>
                    <div class="rounded bg-white/60 p-3 ring-1 ring-gray-100">
                        <dt class="text-xs text-gray-500">{{ ctrans("Sent / failed") }}</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums">
                            {{ report.archived_count.toLocaleString() }}
                            <span :class="report.failed_count ? 'text-red-600' : 'text-gray-400'">/ {{ report.failed_count }}</span>
                        </dd>
                        <dd class="text-xs text-gray-400">{{ ctrans("since PostgreSQL started") }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border p-4" :class="cardClass(report.backup_issue)">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-medium">{{ ctrans("Restore window") }}</span>
                    <span class="flex shrink-0 items-center gap-1 text-xs" :class="backupStatus.text">
                        <span class="h-1.5 w-1.5 rounded-full" :class="backupStatus.dot" />{{ backupStatus.label }}
                    </span>
                </div>
                <div class="mt-0.5 text-xs text-gray-500">{{ ctrans("Any minute in this range can be restored") }}</div>
                <p v-if="report.backup_issue" class="mt-3 text-sm text-red-700">{{ report.backup_issue.replace(/`/g, "") }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded bg-white/60 p-3 ring-1 ring-gray-100">
                        <dt class="text-xs text-gray-500">{{ ctrans("From") }}</dt>
                        <dd class="mt-1 text-sm font-semibold tabular-nums">{{ when(report.restore_from) }}</dd>
                        <dd class="text-xs text-gray-400">{{ ctrans("end of the oldest backup") }}</dd>
                    </div>
                    <div class="rounded bg-white/60 p-3 ring-1 ring-gray-100">
                        <dt class="text-xs text-gray-500">{{ ctrans("To") }}</dt>
                        <dd class="mt-1 text-sm font-semibold tabular-nums">{{ when(report.restore_to) }}</dd>
                        <dd class="text-xs text-gray-400">{{ ctrans("last WAL sent") }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 p-4 sm:col-span-2">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-medium">{{ ctrans("Backups from litio") }}</span>
                    <span v-if="report.backup_running" class="flex shrink-0 items-center gap-1 text-xs text-sky-700">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-sky-500" />{{ ctrans("Backup running") }}
                    </span>
                </div>
                <div class="mt-0.5 text-xs text-gray-500">
                    {{ ctrans("Full on Saturday, incremental the other nights, 02:00 UTC. Reported :ago.", { ago: ago(report.reported_at) }) }}
                </div>
                <ul v-if="report.backups.length" class="mt-3 divide-y divide-gray-100 text-sm">
                    <li v-for="backup in report.backups" :key="`${backup.repo}-${backup.label}`" class="flex items-center gap-x-3 py-1.5">
                        <span class="w-24 shrink-0 whitespace-nowrap text-xs font-medium" :class="backup.type === 'full' ? 'text-gray-900' : 'text-gray-500'">{{ types[backup.type] }}</span>
                        <span class="flex-1 whitespace-nowrap tabular-nums">{{ when(backup.finished_at) }}</span>
                        <span class="whitespace-nowrap text-xs text-gray-500">{{ backup.repo_name }}</span>
                        <span class="whitespace-nowrap text-xs tabular-nums text-gray-500">{{ duration(backup.duration_seconds) }}</span>
                        <span class="w-20 whitespace-nowrap text-right text-xs tabular-nums text-gray-500" v-tooltip="ctrans('Uploaded to :repository', { repository: backup.repo_name })">
                            {{ formatSize(backup.repo_delta ?? backup.repo_size) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-gray-500">{{ ctrans("No backup reported yet.") }}</p>
            </div>
        </div>
    </section>
</template>
