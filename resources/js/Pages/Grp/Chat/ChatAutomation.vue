<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGlobe, faEnvelope, faPaperPlane, faFilter } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    title: string
    pageHead: object
    data: object
}>()

const CHANNEL_ICON: Record<string, object> = {
    website: faGlobe,
    email: faEnvelope,
    whatsapp: faWhatsapp,
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" name="automation" class="mt-5">
        <template #cell(at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.at, { formatTime: "short-datetime" }) }}</span>
        </template>

        <template #cell(kind)="{ item }">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                <FontAwesomeIcon :icon="item.sends_message ? faPaperPlane : faFilter" class="text-xs text-gray-400" fixed-width />
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
            <p v-if="item.sends_message" class="max-w-2xl whitespace-pre-line text-gray-700">{{ item.text }}</p>
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
