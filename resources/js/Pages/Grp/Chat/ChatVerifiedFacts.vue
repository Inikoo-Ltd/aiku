<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 26 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faUndo, faClipboardCheck } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    title: string
    pageHead: object
    data: object
}>()

const STATUS_CLASS: Record<string, string> = {
    active: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    candidate: "bg-amber-50 text-amber-700 ring-amber-200",
    disputed: "bg-red-50 text-red-700 ring-red-200",
    removed: "bg-gray-50 text-gray-500 ring-gray-200",
}

const statusLabel = (item: { status: string, trusted: boolean }) => ({
    active: item.trusted ? ctrans("Trusted") : ctrans("Expired"),
    candidate: ctrans("Checked once"),
    disputed: ctrans("To check again"),
    removed: ctrans("Removed"),
}[item.status] ?? item.status)

const busy = ref<number | null>(null)

const setStatus = (id: number, status: "removed" | "disputed") => {
    busy.value = id
    router.post(route("grp.chat.ai.verified_facts.status", id), { status }, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <p class="mx-4 mt-4 max-w-3xl text-sm text-gray-500">
        {{ ctrans("Shop facts the assistant checked in the tables, the code or our pages, and now uses without checking again. A fact is trusted once it was checked while working two different chats. Remove anything that is wrong: the assistant cannot store it again.") }}
    </p>

    <Table :resource="data" class="mt-3">
        <template #cell(hydrated_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.hydrated_at, { formatTime: "short-datetime" }) }}</span>
        </template>

        <template #cell(shop_name)="{ item }">
            <span class="whitespace-nowrap">{{ item.shop_name }}</span>
        </template>

        <template #cell(status)="{ item }">
            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="item.status === 'active' && !item.trusted ? STATUS_CLASS.removed : STATUS_CLASS[item.status] ?? STATUS_CLASS.removed">
                {{ statusLabel(item) }}
            </span>
        </template>

        <template #cell(title)="{ item }">
            <div class="max-w-2xl space-y-1">
                <p class="font-medium text-gray-800">{{ item.title }}</p>
                <p class="whitespace-pre-line text-gray-700">{{ item.body }}</p>
                <p v-if="item.evidence" class="inline-flex items-start gap-1 text-xs text-gray-500">
                    <FontAwesomeIcon :icon="faClipboardCheck" class="mt-0.5" fixed-width />
                    <span class="break-all">{{ item.evidence }}</span>
                </p>
                <p class="text-xs text-gray-400">{{ ctrans("Checked in :count chats", { count: item.chats_checked }) }}</p>
                <p v-if="item.conflict && item.status !== 'active'" class="text-xs text-red-700">{{ item.conflict }}</p>
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <Button v-if="item.status !== 'removed'" type="tertiary" size="xs" :label="ctrans('Remove')" :loading="busy === item.id" @click="setStatus(item.id, 'removed')">
                <template #icon>
                    <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <Button v-else type="tertiary" size="xs" :label="ctrans('Restore')" :loading="busy === item.id" v-tooltip="ctrans('Puts it back to be checked again before it is trusted')" @click="setStatus(item.id, 'disputed')">
                <template #icon>
                    <FontAwesomeIcon :icon="faUndo" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </Table>
</template>
