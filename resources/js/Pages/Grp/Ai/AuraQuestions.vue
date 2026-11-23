<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPenNib } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { usd } from "@/Composables/formatUsd"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    title: string
    pageHead: object
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" class="mt-5">
        <template #cell(created_at)="{ item }">
            <Link v-if="item.url" :href="item.url" class="primaryLink whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: "short-datetime" }) }}</Link>
            <span v-else class="whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: "short-datetime" }) }}</span>
        </template>

        <template #cell(question)="{ item }">
            <p class="line-clamp-6 max-w-sm whitespace-pre-line text-gray-800">{{ item.question }}</p>
        </template>

        <template #cell(answer)="{ item }">
            <p v-if="!item.answered" class="text-xs text-gray-400">{{ ctrans("Not answered yet") }}</p>
            <p v-else class="line-clamp-8 max-w-md whitespace-pre-line" :class="item.failed ? 'text-gray-400' : 'text-gray-700'">{{ item.answer }}</p>
            <span v-if="item.redrafted" class="mt-1 inline-flex items-center gap-1 whitespace-nowrap text-xs text-pink-600">
                <FontAwesomeIcon :icon="faPenNib" fixed-width aria-hidden="true" />
                {{ ctrans("Redrafted the reply with it") }}
            </span>
        </template>

        <template #cell(lookups)="{ item }">
            <span v-if="!item.lookups.length" class="text-xs text-gray-400">{{ ctrans("Only the chat") }}</span>
            <span v-for="lookup in item.lookups" :key="lookup" class="mr-1 inline-block whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600">{{ lookup }}</span>
        </template>

        <template #cell(cost)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ usd(item.cost) }}</span>
        </template>
    </Table>
</template>
