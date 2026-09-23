<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGlobe, faEnvelope, faPaperPlane, faFilter, faRobot } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    title: string
    pageHead: object
    data: object
}>()

const DRAFT_CLASS: Record<string, string> = {
    pending: "bg-indigo-50 text-indigo-700 ring-indigo-200",
    used: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    edited: "bg-sky-50 text-sky-700 ring-sky-200",
    discarded: "bg-red-50 text-red-700 ring-red-200",
    superseded: "bg-gray-50 text-gray-600 ring-gray-200",
    auto_sent: "bg-violet-50 text-violet-700 ring-violet-200",
}

const flag = (draftId: number) => {
    router.post(route("grp.chat.ai.drafts.flag", [draftId]), {}, { preserveScroll: true })
}

const CHANNEL_ICON: Record<string, object> = {
    website: faGlobe,
    email: faEnvelope,
    whatsapp: faWhatsapp,
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" class="mt-5">
        <template #cell(at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.at, { formatTime: "short-datetime" }) }}</span>
        </template>

        <template #cell(kind)="{ item }">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                <FontAwesomeIcon :icon="item.draft_status ? faRobot : (item.sends_message ? faPaperPlane : faFilter)" class="text-xs text-gray-400" fixed-width />
                {{ item.kind_label }}
            </span>
        </template>

        <template #cell(contact)="{ item }">
            <Link :href="item.url" class="inline-flex items-center gap-1.5 hover:underline">
                <FontAwesomeIcon v-if="CHANNEL_ICON[item.channel]" :icon="CHANNEL_ICON[item.channel]"
                    class="text-xs text-gray-400" v-tooltip="capitalize(item.channel)" fixed-width />
                <span class="truncate max-w-56">{{ item.contact || ctrans("Open conversation") }}</span>
            </Link>
        </template>

        <template #cell(text)="{ item }">
            <div v-if="item.sends_message" class="max-w-2xl">
                <p class="whitespace-pre-line text-gray-700">{{ item.text }}</p>
                <div v-if="item.claim" class="mt-1.5 flex flex-wrap gap-1.5 text-xs">
                    <span class="rounded-full px-2 py-0.5 ring-1 ring-inset"
                        :class="item.claim.order_reference ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200'">
                        {{ item.claim.order_reference ? ctrans("Order :reference", { reference: item.claim.order_reference }) : ctrans("No order number yet") }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 ring-1 ring-inset"
                        :class="item.claim.photos ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200'">
                        {{ item.claim.photos ? ctrans(":count photos or files", { count: item.claim.photos }) : ctrans("No photos yet") }}
                    </span>
                </div>
            </div>
            <div v-else-if="item.draft_status" class="max-w-2xl">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="DRAFT_CLASS[item.draft_status]">
                        {{ item.verdict_label }}
                    </span>
                    <span v-if="item.topic_label" class="text-xs text-gray-500">{{ item.topic_label }}</span>
                    <span v-if="item.reversed" class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200">
                        {{ ctrans("Flagged as wrong") }}
                    </span>
                    <button v-else-if="item.draft_status === 'auto_sent'" type="button" @click="flag(item.draft_id)"
                        class="rounded-md px-2 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200 hover:bg-red-50">
                        {{ ctrans("Flag as wrong") }}
                    </button>
                </div>
                <p class="mt-1 whitespace-pre-line text-gray-700">{{ item.text }}</p>
            </div>
            <div v-else class="max-w-2xl">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="rounded-full px-2 py-0.5 text-xs ring-1 ring-inset"
                        :class="item.is_noise ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200'">
                        {{ item.verdict_label || item.verdict }}
                    </span>
                    <span class="text-xs text-gray-500">
                        {{ item.source === "ai" ? ctrans("AI") : ctrans("Rule") }}<template v-if="item.confidence !== null"> · {{ item.confidence }}%</template>
                    </span>
                    <span v-if="item.put_aside" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                        {{ ctrans("Put aside automatically") }}
                    </span>
                    <span v-if="item.reversed" class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200">
                        {{ ctrans("Overruled by staff") }}
                    </span>
                </div>
                <p v-if="item.text" class="mt-1 text-gray-600">{{ item.text }}</p>
            </div>
        </template>
    </Table>
</template>
