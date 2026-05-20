<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import WhatsAppTemplatePreview from "@/Components/Showcases/Org/Wati/WhatsAppTemplatePreview.vue"

interface WatiTemplateData {
    id: number
    element_name: string
    category: string | null
    status: string | null
    type: string | null
    language: { code?: string; name?: string } | string | null
    quality: string | null
    header: { format?: string; text?: string } | null
    body_original: string | null
    footer: string | null
    buttons: Array<{ text: string; type?: string; url?: string; phone_number?: string }> | null
    buttons_type: string | null
}

defineProps<{
    data: WatiTemplateData
    title: string
    pageHead: PageHeadingTypes
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $t('Template Details') }}</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $t('Name') }}</dt>
                <dd class="text-gray-900 dark:text-gray-100 break-words">{{ data.element_name }}</dd>

                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $t('Category') }}</dt>
                <dd class="capitalize text-gray-900 dark:text-gray-100">{{ data.category ?? '—' }}</dd>

                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $t('Status') }}</dt>
                <dd class="capitalize text-gray-900 dark:text-gray-100">{{ data.status ?? '—' }}</dd>

                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $t('Type') }}</dt>
                <dd class="capitalize text-gray-900 dark:text-gray-100">{{ data.type ?? '—' }}</dd>

                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $t('Language') }}</dt>
                <dd class="text-gray-900 dark:text-gray-100">
                    <template v-if="data.language && typeof data.language === 'object'">
                        {{ (data.language as { name?: string; code?: string }).name ?? (data.language as { code?: string }).code ?? '—' }}
                    </template>
                    <template v-else>{{ data.language ?? '—' }}</template>
                </dd>

                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $t('Quality') }}</dt>
                <dd class="capitalize text-gray-900 dark:text-gray-100">{{ data.quality ?? '—' }}</dd>

                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $t('Buttons Type') }}</dt>
                <dd class="capitalize text-gray-900 dark:text-gray-100">{{ data.buttons_type ?? '—' }}</dd>
            </dl>
        </div>

        <WhatsAppTemplatePreview :data="data" />
    </div>
</template>
