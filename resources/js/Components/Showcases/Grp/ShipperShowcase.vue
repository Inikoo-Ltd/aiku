<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Thu, 25 May 2023 15:03:05 Central European Summer Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUser, faBuilding, faPhone, faGlobe, faLocationArrow, faShippingFast } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import CopyButton from '@/Components/Utils/CopyButton.vue'

library.add(faUser, faBuilding, faPhone, faGlobe, faLocationArrow, faShippingFast)

interface Shipper {
    id: number
    slug: string
    code: string
    name: string
    trade_as: string | null
    contact_name: string | null
    company_name: string | null
    phone: string | null
    website: string | null
    tracking_url: string | null
    label: string | null
}

const props = defineProps<{
    data: { shipper: Shipper }
    tab: string
}>()

const shipper = computed(() => props.data?.shipper)

const details = computed(() => {
    const currentShipper = shipper.value

    return [
        {
            key: 'contact_name',
            label: trans('Contact name'),
            icon: 'fal fa-user',
            value: currentShipper?.contact_name,
            href: null as string | null,
            external: false,
        },
        {
            key: 'company_name',
            label: trans('Company name'),
            icon: 'fal fa-building',
            value: currentShipper?.company_name,
            href: null as string | null,
            external: false,
        },
        {
            key: 'phone',
            label: trans('Phone'),
            icon: 'fal fa-phone',
            value: currentShipper?.phone,
            href: currentShipper?.phone ? `tel:${currentShipper.phone}` : null,
            external: false,
        },
        {
            key: 'website',
            label: trans('Website'),
            icon: 'fal fa-globe',
            value: currentShipper?.website,
            href: currentShipper?.website ?? null,
            external: true,
        },
        {
            key: 'tracking_url',
            label: trans('Tracking URL'),
            icon: 'fal fa-location-arrow',
            value: currentShipper?.tracking_url,
            href: currentShipper?.tracking_url ?? null,
            external: true,
        },
    ].filter((row) => row.value)
})
</script>

<template>
    <div class="px-4 py-6 md:px-6 lg:px-8">
        <div class="max-w-2xl overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <!-- Section: Header -->
            <div class="flex items-center gap-4 border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
                <div class="flex h-14 w-14 flex-none items-center justify-center rounded-full bg-indigo-50 text-indigo-500 ring-1 ring-indigo-500/20">
                    <FontAwesomeIcon icon="fal fa-shipping-fast" class="text-xl" fixed-width aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-base font-semibold text-gray-900">
                            {{ shipper?.name || trans('Unnamed shipper') }}
                        </h2>
                        <span
                            v-if="shipper?.code"
                            class="inline-flex flex-none items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                            {{ shipper?.code }}
                        </span>
                    </div>
                    <p v-if="shipper?.trade_as" class="mt-0.5 truncate text-sm text-gray-500">
                        {{ trans('Trading as') }}: {{ shipper?.trade_as }}
                    </p>
                </div>
            </div>

            <!-- Section: Details -->
            <dl class="divide-y divide-gray-900/5">
                <div v-for="row in details" :key="row.key" class="flex items-center gap-x-4 px-6 py-3.5">
                    <dt v-tooltip="row.label" class="flex-none">
                        <span class="sr-only">{{ row.label }}</span>
                        <FontAwesomeIcon :icon="row.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                    </dt>
                    <dd class="min-w-0 flex-1 text-sm">
                        <a
                            v-if="row.href"
                            :href="row.href"
                            :target="row.external ? '_blank' : undefined"
                            :rel="row.external ? 'noopener noreferrer' : undefined"
                            class="break-all text-gray-700 hover:text-indigo-500 hover:underline">
                            {{ row.value }}
                        </a>
                        <span v-else class="break-all text-gray-700">{{ row.value }}</span>
                    </dd>
                    <CopyButton :text="(row.value as string)" class="flex-none text-gray-400" />
                </div>
            </dl>
        </div>
    </div>
</template>
