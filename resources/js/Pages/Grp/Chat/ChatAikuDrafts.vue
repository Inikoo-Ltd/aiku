<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 21 Oct 2026 10:15:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGlobe, faEnvelope, faExclamationTriangle, faCheck, faBowlingBall, faClipboardCheck } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    title: string
    pageHead: object
    data: object
}>()

const STATUS_CLASS: Record<string, string> = {
    pending: "bg-gray-50 text-gray-600 ring-gray-200",
    used: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    edited: "bg-sky-50 text-sky-700 ring-sky-200",
    discarded: "bg-red-50 text-red-700 ring-red-200",
    superseded: "bg-gray-50 text-gray-500 ring-gray-200",
}

const OUTCOME_CLASS: Record<string, string> = {
    as_is: "bg-green-50 text-green-700 ring-green-200",
    cosmetic: "bg-green-50 text-green-700 ring-green-200",
    gaps_filled: "bg-green-50 text-green-700 ring-green-200",
    small: "bg-green-50 text-green-700 ring-green-200",
    big: "bg-amber-50 text-amber-700 ring-amber-200",
    rewritten: "bg-red-50 text-red-700 ring-red-200",
    own_reply: "bg-red-50 text-red-700 ring-red-200",
    discarded: "bg-red-50 text-red-700 ring-red-200",
}

const channelIcon = (channel: string) => (channel === "whatsapp" ? faWhatsapp : channel === "email" ? faEnvelope : faGlobe)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" class="mt-5">
        <template #cell(at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.at, { formatTime: "short-datetime" }) }}</span>
        </template>

        <template #cell(question)="{ item }">
            <p v-if="item.question" class="line-clamp-6 max-w-sm whitespace-pre-line text-gray-600">{{ item.question }}</p>
            <span v-if="item.intent" class="mt-1 inline-block whitespace-nowrap text-xs text-gray-400">{{ item.intent }}</span>
        </template>

        <template #cell(reply)="{ item }">
            <p v-if="item.reply" class="line-clamp-6 max-w-sm whitespace-pre-line text-gray-600">{{ item.reply }}</p>
        </template>

        <template #cell(outcome)="{ item }">
            <div v-if="item.outcome_label" class="space-y-1">
                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="OUTCOME_CLASS[item.outcome] ?? 'bg-gray-50 text-gray-600 ring-gray-200'">{{ item.outcome_label }}</span>
                <div v-if="item.decided_by" class="whitespace-nowrap text-xs text-gray-400">{{ item.decided_by }}</div>
                <span v-if="item.time_lost" class="inline-block whitespace-nowrap rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200" v-tooltip="ctrans('Jev judged the reply staff typed to say the same as this draft: a right draft written again by hand')">{{ ctrans("Time lost") }}<template v-if="item.time_lost_seconds"> · {{ ctrans(":seconds s", { seconds: item.time_lost_seconds }) }}</template></span>
            </div>
        </template>

        <template #cell(contact)="{ item }">
            <Link :href="item.url" class="inline-flex items-center gap-1.5 text-gray-700 hover:underline" v-tooltip="ctrans('Open the conversation')">
                <FontAwesomeIcon :icon="channelIcon(item.channel)" class="text-xs text-gray-400" fixed-width />
                <span class="max-w-56 truncate">{{ item.contact || ctrans("Open conversation") }}</span>
            </Link>
        </template>

        <template #cell(text)="{ item }">
            <div class="max-w-xl space-y-1">
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="inline-flex items-center gap-1 whitespace-nowrap text-gray-500"><img src="/art/invader.svg" alt="" class="h-3 select-none" />{{ item.kind === "draft" ? ctrans("Draft") : ctrans("Note for staff") }}</span>
                    <span v-if="item.status_label && !item.outcome_label" class="whitespace-nowrap rounded-full px-2 py-0.5 ring-1 ring-inset" :class="STATUS_CLASS[item.status] ?? STATUS_CLASS.pending">{{ item.status_label }}</span>
                    <span v-if="item.checked" class="inline-flex items-center gap-1 text-gray-500"><FontAwesomeIcon :icon="faClipboardCheck" fixed-width />{{ ctrans(":count checked", { count: item.checked }) }}</span>
                    <span v-for="premonition in item.premonitions" :key="premonition" class="inline-flex items-center gap-1 text-gray-500"><FontAwesomeIcon :icon="faBowlingBall" fixed-width />{{ premonition }}</span>
                    <span v-if="item.asked_by" class="text-gray-400">{{ ctrans("asked by :name", { name: item.asked_by }) }}</span>
                </div>
                <p v-if="item.staff_note" class="inline-flex items-start gap-1 rounded border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-xs text-violet-900">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-violet-600" fixed-width />{{ item.staff_note }}
                </p>
                <p class="whitespace-pre-line" :class="item.kind === 'note' ? 'text-pink-900' : 'text-gray-700'">{{ item.text }}</p>
                <div v-if="item.actions?.length" class="flex flex-wrap gap-1.5 text-xs">
                    <span v-for="(action, position) in item.actions" :key="position" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 ring-1 ring-inset"
                        :class="action.done ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-violet-50 text-violet-800 ring-violet-200'">
                        <FontAwesomeIcon v-if="action.done" :icon="faCheck" fixed-width />{{ action.label }}
                    </span>
                </div>
            </div>
        </template>
    </Table>
</template>
