<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faClock, faEnvelope, faFileInvoiceDollar, faMapMarkedAlt, faMoneyBillWaveAlt, faPhone, faUser } from '@fal'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import CopyButton from '@/Components/Utils/CopyButton.vue'

library.add(faClock, faEnvelope, faFileInvoiceDollar, faMapMarkedAlt, faMoneyBillWaveAlt, faPhone, faUser)

const props = defineProps<{
    contactCard: {
        contact?: string
        location?: string[]
        email?: string
        phone?: string
        currency?: { code?: string, name?: string }
        address?: { formatted_address?: string }
        supplierInfo?: {
            delivery_time?: number
            incoterm?: string
            port_of_export?: string
            port_of_import?: string
        }
    }
}>()

const addressText = computed(() => props.contactCard.address?.formatted_address?.replace(/<br\s*\/?>/gi, ', ').replace(/<[^>]+>/g, '') ?? null)

const info = computed(() => props.contactCard.supplierInfo)

const ports = computed(() => [info.value?.port_of_export, info.value?.port_of_import].filter(Boolean).join(' → '))
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700">
        <span v-if="contactCard.location?.length" v-tooltip="addressText" class="inline-flex items-center">
            <AddressLocation :data="contactCard.location" />
        </span>
        <span v-if="contactCard.contact" class="inline-flex items-center gap-1.5">
            <FontAwesomeIcon v-tooltip="ctrans('Contact name')" icon="fal fa-user" class="text-gray-400" fixed-width aria-hidden="true" />
            {{ contactCard.contact }}
        </span>
        <span v-if="contactCard.email" class="inline-flex items-center gap-1.5">
            <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width aria-hidden="true" />
            <a :href="`mailto:${contactCard.email}`" class="hover:underline">{{ contactCard.email }}</a>
            <CopyButton :text="contactCard.email" class="text-gray-400" />
        </span>
        <span v-if="contactCard.phone" class="inline-flex items-center gap-1.5">
            <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true" />
            <a :href="`tel:${contactCard.phone}`" class="hover:underline">{{ contactCard.phone }}</a>
            <CopyButton :text="contactCard.phone" class="text-gray-400" />
        </span>
        <span v-if="contactCard.currency?.code" v-tooltip="contactCard.currency.name" class="inline-flex items-center gap-1.5">
            <FontAwesomeIcon icon="fal fa-money-bill-wave-alt" class="text-gray-400" fixed-width aria-hidden="true" />
            {{ contactCard.currency.code }}
        </span>
        <span v-if="info?.incoterm" v-tooltip="ctrans('Incoterm')" class="inline-flex items-center gap-1.5">
            <FontAwesomeIcon icon="fal fa-file-invoice-dollar" class="text-gray-400" fixed-width aria-hidden="true" />
            {{ info.incoterm }}
        </span>
        <span v-if="ports" v-tooltip="ctrans('Ports')" class="inline-flex items-center gap-1.5">
            <FontAwesomeIcon icon="fal fa-map-marked-alt" class="text-gray-400" fixed-width aria-hidden="true" />
            {{ ports }}
        </span>
        <span v-if="info?.delivery_time" v-tooltip="ctrans('Delivery time')" class="inline-flex items-center gap-1.5">
            <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
            {{ ctrans(':days days', { days: info.delivery_time }) }}
        </span>
    </div>
</template>
