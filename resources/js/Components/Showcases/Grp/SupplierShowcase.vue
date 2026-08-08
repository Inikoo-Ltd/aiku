<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 22 May 2023 10:35:34 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject } from 'vue'
import { Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
    faBoxUsd,
    faBuilding,
    faCalendarPlus,
    faClipboardList,
    faEnvelope,
    faGlobe,
    faHashtag,
    faMapMarkedAlt,
    faMapMarkerAlt,
    faMoneyBillWaveAlt,
    faPersonDolly,
    faPhone,
    faTruckContainer,
    faUser
} from '@fal'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import CopyButton from '@/Components/Utils/CopyButton.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

library.add(
    faBoxUsd,
    faBuilding,
    faCalendarPlus,
    faClipboardList,
    faEnvelope,
    faGlobe,
    faHashtag,
    faMapMarkedAlt,
    faMapMarkerAlt,
    faMoneyBillWaveAlt,
    faPersonDolly,
    faPhone,
    faTruckContainer,
    faUser
)

const props = defineProps<{
    data: {
        contactCard: {
            created_at?: string
            company?: string
            contact?: string
            location?: string[]
            email?: string
            phone?: string
            website?: string
            currency?: {
                code?: string
                symbol?: string
                name?: string
            }
            address?: {
                formatted_address?: string
            }
            image_id?: number | null
        }
        stats: {
            label: string
            icon?: string
            count: number
            route?: {
                name: string
                parameters?: Record<string, any>
            }
        }[]
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const contactCard = computed(() => props.data?.contactCard)

const title = computed(
    () => contactCard.value?.company || contactCard.value?.contact || trans('Supplier')
)

const currencyLabel = computed(() => {
    const currency = contactCard.value?.currency

    if (!currency?.code) {
        return null
    }

    return currency.symbol ? `${currency.code} ${currency.symbol}` : currency.code
})

const hasAddress = computed(() =>
    Boolean(contactCard.value?.location?.length || contactCard.value?.address?.formatted_address)
)

const details = computed(() =>
    [
        {
            key: 'company',
            label: trans('Company'),
            icon: 'fal fa-building',
            value: contactCard.value?.company,
            href: null as string | null,
            external: false,
            copyable: true
        },
        {
            key: 'contact',
            label: trans('Contact name'),
            icon: 'fal fa-user',
            value: contactCard.value?.contact,
            href: null as string | null,
            external: false,
            copyable: true
        },
        {
            key: 'email',
            label: trans('Email'),
            icon: 'fal fa-envelope',
            value: contactCard.value?.email,
            href: contactCard.value?.email ? `mailto:${contactCard.value.email}` : null,
            external: false,
            copyable: true
        },
        {
            key: 'phone',
            label: trans('Phone'),
            icon: 'fal fa-phone',
            value: contactCard.value?.phone,
            href: contactCard.value?.phone ? `tel:${contactCard.value.phone}` : null,
            external: false,
            copyable: true
        },
        {
            key: 'website',
            label: trans('Website'),
            icon: 'fal fa-globe',
            value: contactCard.value?.website,
            href: contactCard.value?.website ?? null,
            external: true,
            copyable: true
        },
        {
            key: 'currency',
            label: trans('Currency'),
            icon: 'fal fa-money-bill-wave-alt',
            value: contactCard.value?.currency?.name,
            href: null as string | null,
            external: false,
            copyable: false
        },
        {
            key: 'created_at',
            label: trans('Created at'),
            icon: 'fal fa-calendar-plus',
            value: contactCard.value?.created_at
                ? useFormatTime(contactCard.value.created_at, { formatTime: 'aiku' })
                : null,
            href: null as string | null,
            external: false,
            copyable: false
        }
    ].filter((row) => row.value)
)
</script>

<template>
    <div class="space-y-6 px-4 py-6 md:px-6 lg:px-8">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <component
                :is="stat.route?.name ? Link : 'div'"
                v-for="stat in data?.stats"
                :key="stat.label"
                :href="stat.route?.name ? route(stat.route.name, stat.route.parameters) : undefined"
                class="flex items-center gap-4 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-900/5"
                :class="stat.route?.name ? 'hover:bg-gray-50' : ''">
                <div
                    class="flex h-11 w-11 flex-none items-center justify-center rounded-lg bg-indigo-50 text-indigo-500 ring-1 ring-indigo-500/20">
                    <FontAwesomeIcon :icon="stat.icon || 'fal fa-hashtag'" fixed-width aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <dt class="truncate text-xs font-medium uppercase tracking-wide text-gray-400">
                        {{ stat.label }}
                    </dt>
                    <dd class="text-2xl font-bold leading-8 tracking-tight text-gray-900">
                        {{ locale.number(stat.count ?? 0) }}
                    </dd>
                </div>
            </component>
        </dl>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                <div class="flex items-center gap-4 border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
                    <div
                        class="flex h-14 w-14 flex-none items-center justify-center rounded-full bg-indigo-50 text-indigo-500 ring-1 ring-indigo-500/20">
                        <FontAwesomeIcon icon="fal fa-person-dolly" class="text-xl" fixed-width aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-base font-semibold text-gray-900">{{ title }}</h2>
                            <span
                                v-if="currencyLabel"
                                v-tooltip="contactCard?.currency?.name"
                                class="inline-flex flex-none items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                                {{ currencyLabel }}
                            </span>
                        </div>
                        <p v-if="contactCard?.location?.length" class="mt-0.5 truncate text-sm text-gray-500">
                            <AddressLocation :data="contactCard.location" />
                        </p>
                    </div>
                </div>

                <dl v-if="details.length" class="divide-y divide-gray-900/5">
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
                        <CopyButton
                            v-if="row.copyable"
                            :text="(row.value as string)"
                            class="flex-none text-gray-400" />
                    </div>
                </dl>
                <p v-else class="px-6 py-8 text-center text-sm text-gray-400">
                    {{ trans('No contact details recorded') }}
                </p>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                <div class="flex items-center gap-3 border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
                    <FontAwesomeIcon
                        icon="fal fa-map-marked-alt"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true" />
                    <h2 class="text-base font-semibold text-gray-900">{{ trans('Address') }}</h2>
                </div>

                <div v-if="hasAddress" class="space-y-4 px-6 py-5 text-sm text-gray-700">
                    <div v-if="contactCard?.location?.length" class="flex items-center gap-x-3">
                        <FontAwesomeIcon
                            icon="fal fa-map-marker-alt"
                            class="text-gray-400"
                            fixed-width
                            aria-hidden="true" />
                        <AddressLocation :data="contactCard.location" />
                    </div>
                    <div
                        v-if="contactCard?.address?.formatted_address"
                        class="rounded-lg bg-gray-50 px-4 py-3 leading-relaxed ring-1 ring-inset ring-gray-900/5"
                        v-html="contactCard.address.formatted_address" />
                </div>
                <p v-else class="px-6 py-8 text-center text-sm text-gray-400">
                    {{ trans('No address recorded') }}
                </p>
            </div>
        </div>
    </div>
</template>
