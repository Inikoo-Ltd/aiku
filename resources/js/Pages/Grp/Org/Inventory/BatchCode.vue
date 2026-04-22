<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    batch_code: {
        id: number
        code: string
        expiry_date: string | null
        org_stock_id: number | null
        org_stock_code: string | null
        org_stock_name: string | null
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mt-6 px-4 max-w-2xl">
        <dl class="divide-y divide-gray-100">
            <div class="py-3 flex gap-x-4">
                <dt class="w-40 text-sm font-medium text-gray-500">{{ $t('Code') }}</dt>
                <dd class="text-sm text-gray-900">{{ batch_code.code }}</dd>
            </div>
            <div class="py-3 flex gap-x-4">
                <dt class="w-40 text-sm font-medium text-gray-500">{{ $t('Expiry Date') }}</dt>
                <dd class="text-sm text-gray-900">{{ batch_code.expiry_date ?? '—' }}</dd>
            </div>
            <div v-if="batch_code.org_stock_code" class="py-3 flex gap-x-4">
                <dt class="w-40 text-sm font-medium text-gray-500">{{ $t('SKU') }}</dt>
                <dd class="text-sm text-gray-900">
                    {{ batch_code.org_stock_code }}
                    <span v-if="batch_code.org_stock_name" class="text-gray-500 ml-1">— {{ batch_code.org_stock_name }}</span>
                </dd>
            </div>
        </dl>
    </div>
</template>
