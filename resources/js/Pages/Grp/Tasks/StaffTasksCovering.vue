<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { LeaveCover } from "@/types/LeaveCover"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserFriends, faTasks, faLifeRing, faComments, faDolly, faKey } from "@fal"
library.add(faUserFriends, faTasks, faLifeRing, faComments, faDolly, faKey)

type WorkItem = { reference: string; title: string; status: string; url: string | null }

type CoveredWork = { key: string; label: string; icon: string; items: WorkItem[] }

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    covers: (Omit<LeaveCover, "open_work"> & { work: CoveredWork[] })[]
}>()

const workToTakeOver = (work: CoveredWork[]) => work.filter((kind) => kind.items.length)
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4">
        <div v-if="!covers.length" class="py-10 text-center text-sm text-gray-400">
            {{ ctrans("You are not covering anyone right now") }}
        </div>

        <section v-for="cover in covers" :id="`cover-${cover.id}`" :key="cover.id" class="scroll-mt-20 rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 px-4 py-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-x-2">
                        <span class="font-semibold text-gray-900">{{ cover.employee_name }}</span>
                        <span
                            class="rounded px-1.5 py-0.5 text-xs font-medium"
                            :class="cover.is_ongoing ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                            {{ cover.is_ongoing ? ctrans("Now") : ctrans("Upcoming") }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ cover.type_label }} ·
                        {{ useFormatTime(cover.start_date, { formatTime: "d MMM" }) }} – {{ useFormatTime(cover.end_date, { formatTime: "d MMM yyyy" }) }}
                    </div>
                    <div class="mt-1.5 flex flex-wrap gap-1">
                        <span v-for="jobPosition in cover.job_positions" :key="jobPosition" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">
                            {{ jobPosition }}
                        </span>
                    </div>
                </div>
                <span v-if="cover.has_permissions" class="flex items-center gap-x-1 text-xs text-amber-600">
                    <FontAwesomeIcon icon="fal fa-key" fixed-width aria-hidden="true" />
                    {{ ctrans("You can use their permissions") }}
                </span>
            </header>

            <div v-if="!workToTakeOver(cover.work).length" class="px-4 py-4 text-sm text-gray-400">
                {{ ctrans("Nothing open is assigned to them") }}
            </div>

            <div v-else class="grid gap-4 px-4 py-3 md:grid-cols-2">
                <div v-for="kind in workToTakeOver(cover.work)" :key="kind.key">
                    <p class="mb-1 flex items-center gap-x-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                        <FontAwesomeIcon :icon="kind.icon" fixed-width aria-hidden="true" />
                        {{ kind.label }}
                        <span class="rounded-full bg-gray-100 px-1.5 tabular-nums text-gray-600">{{ kind.items.length }}</span>
                    </p>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="(item, index) in kind.items" :key="`${kind.key}-${index}`">
                            <component
                                :is="item.url ? Link : 'div'"
                                :href="item.url ?? undefined"
                                class="flex items-center justify-between gap-x-3 rounded px-1 py-1.5 text-sm"
                                :class="item.url && 'hover:bg-gray-50'">
                                <span class="flex min-w-0 items-center gap-x-2">
                                    <span class="shrink-0 font-mono text-xs text-[--app-accent-strong]">{{ item.reference }}</span>
                                    <span class="truncate text-gray-800">{{ item.title }}</span>
                                </span>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ item.status }}</span>
                            </component>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</template>
