<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 22 May 2023 10:35:34 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
    faBoxUsd,
    faBuilding,
    faCalendarPlus,
    faCalendarTimes,
    faClock,
    faHourglassHalf,
    faIndustry,
    faPencil,
    faSnowflake,
    faStopwatch,
    faEnvelope,
    faFileInvoiceDollar,
    faGlobe,
    faHashtag,
    faMapMarkedAlt,
    faMapMarkerAlt,
    faMoneyBillWaveAlt,
    faPersonDolly,
    faPhone,
    faTag,
    faTruckContainer,
    faUser
} from '@fal'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import CopyButton from '@/Components/Utils/CopyButton.vue'
import ZoomableImage from '@/Components/Utils/ZoomableImage.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import SupplierRelationshipContact from '@/Components/SupplyChain/SupplierRelationshipContact.vue'

library.add(
    faBoxUsd,
    faBuilding,
    faCalendarPlus,
    faCalendarTimes,
    faClock,
    faHourglassHalf,
    faIndustry,
    faPencil,
    faSnowflake,
    faStopwatch,
    faEnvelope,
    faFileInvoiceDollar,
    faGlobe,
    faHashtag,
    faMapMarkedAlt,
    faMapMarkerAlt,
    faMoneyBillWaveAlt,
    faPersonDolly,
    faPhone,
    faTag,
    faTruckContainer,
    faUser
)

type RouteLink = {
    name: string
    parameters: Record<string, string | number>
}

type FactoryHoliday = {
    from: string
    to?: string | null
    note?: string | null
}

const props = defineProps<{
    data: {
        leadTimes?: {
            delivery_time?: number | string | null
            production_waiting_time?: number | string | null
            cooling_period?: number | string | null
            pre_order_lead_time?: number | string | null
            pre_order_lead_time_unit?: 'days' | 'weeks' | null
            factory_holidays?: FactoryHoliday[]
            edit_routes?: {
                settings: RouteLink
                factory: RouteLink
                pre_orders: RouteLink
            } | null
        }
        relationshipContact?: InstanceType<typeof SupplierRelationshipContact>['$props']['data']
        contactCard: {
            created_at?: string
            company?: string
            nickname?: string | null
            contact?: string
            location?: string[]
            email?: string
            extra_emails?: string[]
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
            photo?: Record<string, any> | null
            photo_full?: { original_url: string; download_url: string } | null
            supplierInfo?: {
                delivery_type?: string
                delivery_time?: number
                production_waiting_time?: number
                incoterm?: string
                port_of_export?: string
                port_of_import?: string
                products_origin?: string
                payment_terms?: string
                minimum_order?: number
                cooling_period?: number
                next_purchase_order_reference?: string
                next_stock_delivery_reference?: string
            }
        }
    }
}>()

const contactCard = computed(() => props.data?.contactCard)

const title = computed(
    () => contactCard.value?.company || contactCard.value?.contact || ctrans('Supplier')
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
            label: ctrans('Company'),
            icon: 'fal fa-building',
            value: contactCard.value?.company,
            href: null as string | null,
            external: false,
            copyable: true
        },
        {
            key: 'nickname',
            label: ctrans('Nickname'),
            icon: 'fal fa-tag',
            value: contactCard.value?.nickname,
            href: null as string | null,
            external: false,
            copyable: true
        },
        {
            key: 'contact',
            label: ctrans('Contact name'),
            icon: 'fal fa-user',
            value: contactCard.value?.contact,
            href: null as string | null,
            external: false,
            copyable: true
        },
        {
            key: 'email',
            label: ctrans('Email'),
            icon: 'fal fa-envelope',
            value: contactCard.value?.email,
            href: contactCard.value?.email ? `mailto:${contactCard.value.email}` : null,
            external: false,
            copyable: true
        },
        ...(contactCard.value?.extra_emails ?? []).map((email, index) => ({
            key: `extra_email_${index}`,
            label: ctrans('Other email'),
            icon: 'fal fa-envelope',
            value: email,
            href: `mailto:${email}` as string | null,
            external: false,
            copyable: true
        })),
        {
            key: 'phone',
            label: ctrans('Phone'),
            icon: 'fal fa-phone',
            value: contactCard.value?.phone,
            href: contactCard.value?.phone ? `tel:${contactCard.value.phone}` : null,
            external: false,
            copyable: true
        },
        {
            key: 'website',
            label: ctrans('Website'),
            icon: 'fal fa-globe',
            value: contactCard.value?.website,
            href: contactCard.value?.website ?? null,
            external: true,
            copyable: true
        },
        {
            key: 'currency',
            label: ctrans('Currency'),
            icon: 'fal fa-money-bill-wave-alt',
            value: contactCard.value?.currency?.name,
            href: null as string | null,
            external: false,
            copyable: false
        },
        {
            key: 'created_at',
            label: ctrans('Created at'),
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

const supplierInfo = computed(() => {
    const info = contactCard.value?.supplierInfo

    if (!info) {
        return []
    }

    return [
        {
            key: 'delivery_type',
            label: ctrans('Delivery type'),
            icon: 'fal fa-truck-container',
            value: info.delivery_type === 'container' ? ctrans('Container') : info.delivery_type === 'parcel' ? ctrans('Parcels') : info.delivery_type
        },
        {
            key: 'products_origin',
            label: ctrans('Products origin'),
            icon: 'fal fa-globe',
            value: info.products_origin
        },
        {
            key: 'incoterm',
            label: ctrans('Incoterm'),
            icon: 'fal fa-file-invoice-dollar',
            value: info.incoterm
        },
        {
            key: 'ports',
            label: ctrans('Ports'),
            icon: 'fal fa-map-marked-alt',
            value: info.port_of_export && info.port_of_import ? `${info.port_of_export} → ${info.port_of_import}` : info.port_of_export || info.port_of_import
        },
        {
            key: 'payment_terms',
            label: ctrans('Payment terms'),
            icon: 'fal fa-file-invoice-dollar',
            value: info.payment_terms
        },
        {
            key: 'minimum_order',
            label: ctrans('Minimum order'),
            icon: 'fal fa-box-usd',
            value: info.minimum_order
        },
        {
            key: 'next_purchase_order_reference',
            label: ctrans('Next order number'),
            icon: 'fal fa-hashtag',
            value: info.next_purchase_order_reference
        },
        {
            key: 'next_stock_delivery_reference',
            label: ctrans('Next delivery number'),
            icon: 'fal fa-hashtag',
            value: info.next_stock_delivery_reference
        }
    ].filter((row) => row.value)
})

const leadTimes = computed(() => props.data?.leadTimes)

const hasValue = (value: number | string | null | undefined) => value !== null && value !== undefined && value !== ''

const formatDays = (value: number | string | null | undefined) => (hasValue(value) ? ctrans(':days days', { days: value as number }) : null)

const leadTimeRows = computed(() => {
    const times = leadTimes.value

    if (!times) {
        return []
    }

    const preOrderLeadTime = hasValue(times.pre_order_lead_time)
        ? times.pre_order_lead_time_unit === 'weeks'
            ? ctrans(':weeks weeks', { weeks: times.pre_order_lead_time as number })
            : formatDays(times.pre_order_lead_time)
        : null

    return [
        {
            key: 'delivery_time',
            label: ctrans('Delivery time'),
            icon: 'fal fa-clock',
            value: formatDays(times.delivery_time),
            editRoute: times.edit_routes?.settings
        },
        {
            key: 'production_waiting_time',
            label: ctrans('Production time'),
            icon: 'fal fa-industry',
            value: formatDays(times.production_waiting_time),
            editRoute: times.edit_routes?.settings
        },
        {
            key: 'cooling_period',
            label: ctrans('Cooling period between orders'),
            icon: 'fal fa-snowflake',
            value: formatDays(times.cooling_period),
            editRoute: times.edit_routes?.settings
        },
        {
            key: 'pre_order_lead_time',
            label: ctrans('Pre-order lead time'),
            icon: 'fal fa-hourglass-half',
            value: preOrderLeadTime,
            editRoute: times.edit_routes?.pre_orders
        }
    ]
})

const formatHoliday = (holiday: FactoryHoliday) => {
    const from = useFormatTime(holiday.from)

    return holiday.to && holiday.to !== holiday.from ? `${from} – ${useFormatTime(holiday.to)}` : from
}
</script>

<template>
    <div class="space-y-6 px-4 py-6 md:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                <div class="flex items-center gap-4 border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
                    <div
                        class="flex h-14 w-14 flex-none items-center justify-center overflow-hidden rounded-full bg-gray-50 text-gray-500 ring-1 ring-gray-300">
                        <ZoomableImage
                            v-if="contactCard?.photo"
                            :src="contactCard.photo"
                            :full="contactCard.photo_full"
                            :alt="title"
                            class="h-full w-full rounded-full border-0" />
                        <FontAwesomeIcon v-else icon="fal fa-person-dolly" class="text-xl" fixed-width aria-hidden="true" />
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
                                class="break-all text-gray-700 hover:text-gray-900 hover:underline">
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
                    {{ ctrans('No contact details recorded') }}
                </p>

                <SupplierRelationshipContact
                    v-if="data.relationshipContact"
                    :data="data.relationshipContact"
                    class="border-t border-gray-900/5" />

                <template v-if="supplierInfo.length">
                    <div class="border-t border-gray-900/5 bg-gray-50/80 px-6 py-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-gray-400">
                            {{ ctrans('Supplying') }}
                        </h3>
                    </div>
                    <dl class="divide-y divide-gray-900/5">
                        <div v-for="row in supplierInfo" :key="row.key" class="flex items-center gap-x-4 px-6 py-3.5">
                            <dt v-tooltip="row.label" class="flex-none">
                                <span class="sr-only">{{ row.label }}</span>
                                <FontAwesomeIcon :icon="row.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="min-w-0 flex-1 text-sm text-gray-700">
                                <span class="text-gray-400">{{ row.label }}:</span> {{ row.value }}
                            </dd>
                        </div>
                    </dl>
                </template>
            </div>

            <div class="space-y-6">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                    <div class="flex items-center gap-3 border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
                        <FontAwesomeIcon
                            icon="fal fa-map-marked-alt"
                            class="text-gray-400"
                            fixed-width
                            aria-hidden="true" />
                        <h2 class="text-base font-semibold text-gray-900">{{ ctrans('Address') }}</h2>
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
                        {{ ctrans('No address recorded') }}
                    </p>
                </div>

                <div v-if="leadTimes" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                    <div class="flex items-center gap-3 border-b border-gray-900/5 bg-gray-50/80 px-6 py-4">
                        <FontAwesomeIcon icon="fal fa-stopwatch" class="text-gray-400" fixed-width aria-hidden="true" />
                        <h2 class="flex-1 text-base font-semibold text-gray-900">{{ ctrans('Lead times') }}</h2>
                        <Link v-if="leadTimes.edit_routes" :href="route(leadTimes.edit_routes.settings.name, leadTimes.edit_routes.settings.parameters)">
                            <Button type="tertiary" size="xs" :label="ctrans('Edit')" icon="fal fa-pencil" />
                        </Link>
                    </div>

                    <dl class="divide-y divide-gray-900/5">
                        <div v-for="row in leadTimeRows" :key="row.key" class="group flex items-center gap-x-4 px-6 py-3">
                            <dt class="flex flex-1 items-center gap-x-4 text-sm text-gray-500">
                                <FontAwesomeIcon :icon="row.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                                {{ row.label }}
                            </dt>
                            <dd class="text-sm font-medium" :class="row.value ? 'text-gray-900' : 'italic text-gray-400 font-normal'">
                                {{ row.value ?? ctrans('Not set') }}
                            </dd>
                            <Link
                                v-if="row.editRoute"
                                v-tooltip="ctrans('Edit')"
                                :href="route(row.editRoute.name, row.editRoute.parameters)"
                                class="flex-none text-gray-300 transition-colors hover:text-[var(--app-accent)] focus:text-[var(--app-accent)] group-hover:text-gray-400">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                                <span class="sr-only">{{ ctrans('Edit') }} {{ row.label }}</span>
                            </Link>
                        </div>

                        <div class="group flex items-start gap-x-4 px-6 py-3">
                            <dt class="flex flex-1 items-center gap-x-4 text-sm text-gray-500">
                                <FontAwesomeIcon icon="fal fa-calendar-times" class="text-gray-400" fixed-width aria-hidden="true" />
                                {{ ctrans('Upcoming factory holidays') }}
                            </dt>
                            <dd class="text-right text-sm">
                                <template v-if="leadTimes.factory_holidays?.length">
                                    <div v-for="(holiday, index) in leadTimes.factory_holidays" :key="index" class="font-medium text-gray-900">
                                        {{ formatHoliday(holiday) }}
                                        <span v-if="holiday.note" class="font-normal text-gray-500">· {{ holiday.note }}</span>
                                    </div>
                                </template>
                                <span v-else class="italic text-gray-400">{{ ctrans('None') }}</span>
                            </dd>
                            <Link
                                v-if="leadTimes.edit_routes"
                                v-tooltip="ctrans('Edit')"
                                :href="route(leadTimes.edit_routes.factory.name, leadTimes.edit_routes.factory.parameters)"
                                class="flex-none text-gray-300 transition-colors hover:text-[var(--app-accent)] focus:text-[var(--app-accent)] group-hover:text-gray-400">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                                <span class="sr-only">{{ ctrans('Edit') }} {{ ctrans('Factory holidays') }}</span>
                            </Link>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</template>
