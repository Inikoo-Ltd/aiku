<!--
  - Author: andiferdiawan (https://github.com/andiferdiawan)
  - Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, andiferdiawan
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCheck,
    faTimes,
    faLink,
    faUnlink,
    faComments,
    faPen,
    faEye,
    faTrashAlt,
    faSync,
    faSpinner,
} from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Link } from "@inertiajs/vue3"
import { RouteParams } from "@/types/route-params"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { ref } from "vue"

library.add(faCheck, faTimes, faLink, faUnlink, faComments, faPen, faEye, faTrashAlt, faSync, faSpinner)

const props = defineProps<{
    data: object
    tab?: string
}>()

const loadingRelink = ref<Set<number>>(new Set())
const loadingDelete = ref<Set<number>>(new Set())

function customerRoute(customerSlug?: string): string | null {
    if (!customerSlug) return null
    try {
        const params = route().params as RouteParams
        return route("grp.org.shops.show.crm.customers.show", [
            params.organisation,
            params.shop,
            customerSlug,
        ])
    } catch {
        return null
    }
}

function whatsappChatUrl(waId?: string): string | null {
    if (!waId) return null
    const digits = waId.replace(/\D/g, '')
    return `https://wa.me/${digits}`
}

async function relinkContact(contact: any): Promise<void> {
    if (!contact.routes?.relink) return
    loadingRelink.value = new Set([...loadingRelink.value, contact.id])
    try {
        const { data } = await axios.post(contact.routes.relink)
        notify({
            title: data.customer_id ? "Contact relinked to customer" : "No matching customer found",
            type: data.customer_id ? "success" : "warn",
        })
    } catch {
        notify({ title: "Failed to relink contact", type: "error" })
    } finally {
        loadingRelink.value.delete(contact.id)
        loadingRelink.value = new Set(loadingRelink.value)
    }
}

async function deleteContact(contact: any): Promise<void> {
    if (!contact.routes?.delete) return
    loadingDelete.value = new Set([...loadingDelete.value, contact.id])
    try {
        await axios.delete(contact.routes.delete)
        notify({ title: "Contact removed from local database", type: "success" })
    } catch {
        notify({ title: "Failed to delete contact", type: "error" })
    } finally {
        loadingDelete.value.delete(contact.id)
        loadingDelete.value = new Set(loadingDelete.value)
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: contact }">
            <span class="font-medium text-sm text-gray-800">{{ contact.name }}</span>
        </template>

        <template #cell(phone)="{ item: contact }">
            <span class="text-sm font-mono text-gray-600">+{{ contact.phone }}</span>
        </template>

        <template #cell(contact_status)="{ item: contact }">
            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="contact.contact_status === 'valid'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700'"
            >
                {{ contact.contact_status }}
            </span>
        </template>

        <template #cell(opted_in)="{ item: contact }">
            <FontAwesomeIcon
                :icon="contact.opted_in ? faCheck : faTimes"
                :class="contact.opted_in ? 'text-green-500' : 'text-gray-300'"
                fixed-width
            />
        </template>

        <template #cell(allow_broadcast)="{ item: contact }">
            <FontAwesomeIcon
                :icon="contact.allow_broadcast ? faCheck : faTimes"
                :class="contact.allow_broadcast ? 'text-green-500' : 'text-gray-300'"
                fixed-width
            />
        </template>

        <template #cell(actions)="{ item: contact }">
            <div class="flex items-center gap-1">

                <!-- Customer Link Status -->
                <component :is="contact.customer?.slug ? Link : 'span'" :href="contact.customer?.slug
                    ? customerRoute(contact.customer.slug) ?? '#'
                    : undefined" :class="contact.customer?.slug
                    ? 'p-1.5 rounded text-indigo-600 bg-indigo-50 transition-colors'
                    : 'p-1.5 rounded text-gray-400 bg-gray-50 transition-colors'" v-tooltip="contact.customer?.slug
                    ? `Linked: ${contact.customer?.name}`
                    : 'Not linked'">
                    <FontAwesomeIcon :icon="contact.customer?.slug ? faLink : faUnlink" class="text-sm" fixed-width />
                </component>

                <!-- Chat -->
                <a v-if="contact.wa_id" :href="whatsappChatUrl(contact.wa_id) ?? '#'" target="_blank"
                    rel="noopener noreferrer" class="p-1.5 rounded text-green-600 bg-green-50 transition-colors"
                    v-tooltip="'Open WhatsApp Chat'">
                    <FontAwesomeIcon :icon="faComments" class="text-sm" fixed-width />
                </a>

                <!-- Relink -->
                <button class="p-1.5 rounded text-blue-600 bg-blue-50 transition-colors disabled:opacity-40"
                    :disabled="loadingRelink.has(contact.id)" v-tooltip="'Relink to Customer'"
                    @click="relinkContact(contact)">
                    <FontAwesomeIcon :icon="loadingRelink.has(contact.id) ? faSpinner : faSync"
                        :class="loadingRelink.has(contact.id) ? 'animate-spin' : ''" class="text-sm" fixed-width />
                </button>

                <!-- Delete -->
                <button v-if="contact.routes?.delete"
                    class="p-1.5 rounded text-red-600 bg-red-50 transition-colors disabled:opacity-40"
                    :disabled="loadingDelete.has(contact.id)" v-tooltip="'Remove from local database'"
                    @click="deleteContact(contact)">
                    <FontAwesomeIcon :icon="loadingDelete.has(contact.id) ? faSpinner : faTrashAlt"
                        :class="loadingDelete.has(contact.id) ? 'animate-spin' : ''" class="text-sm" fixed-width />
                </button>

            </div>
        </template>
    </Table>
</template>
