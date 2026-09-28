<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import Select from "primevue/select"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInboxIn, faPaperPlane, faPaperclip, faPersonDolly } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import EmailBody from "@/Components/Chat/EmailBody.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

interface EmailAddress {
    name: string | null
    address: string | null
}

interface ThreadMessage {
    id: number
    is_outbound: boolean
    from: EmailAddress
    to: EmailAddress[]
    cc: EmailAddress[]
    sent_at: string
    body_html: string | null
    body_text: string | null
    attachments: { name: string; size: number; mime_type: string; url: string }[]
    delivery: { state: string; reads: number; clicks: number } | null
    purchase_order: { reference: string; route: routeType } | null
}

const props = defineProps<{
    title: string
    pageHead: object
    supplier: { name: string; code: string; routed_by: string | null; route: routeType } | null
    assign: { route: routeType; options: { value: number; label: string }[] } | null
    messages: ThreadMessage[]
}>()

const formatted = ref<Record<number, boolean>>({})
const chosenSupplier = ref<number | null>(null)
const isAssigning = ref(false)
const isReassigning = ref(false)

const addressList = (addresses: EmailAddress[]) => addresses.map((address) => address.name || address.address).join(", ")

const fileSize = (bytes: number) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`)

const assignSupplier = () => {
    if (!props.assign || !chosenSupplier.value) {
        return
    }

    router.post(route(props.assign.route.name, props.assign.route.parameters), { org_supplier_id: chosenSupplier.value }, {
        preserveScroll: true,
        onStart: () => (isAssigning.value = true),
        onFinish: () => (isAssigning.value = false),
        onSuccess: () => (isReassigning.value = false),
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-auto max-w-4xl space-y-4 px-4 py-4">
        <div class="flex flex-wrap items-center gap-3 rounded-md border border-gray-200 bg-white px-4 py-3">
            <FontAwesomeIcon :icon="faPersonDolly" class="text-gray-400" fixed-width />
            <template v-if="supplier && !isReassigning">
                <Link :href="route(supplier.route.name, supplier.route.parameters)" class="primaryLink font-medium">{{ supplier.name }}</Link>
                <span class="text-xs text-gray-500">{{ supplier.code }}</span>
                <span v-if="supplier.routed_by === 'manual'" class="text-xs text-gray-400">· {{ ctrans("assigned by hand") }}</span>
                <Button v-if="assign" :label="ctrans('Change')" type="tertiary" size="xs" class="ml-auto" @click="isReassigning = true" />
            </template>
            <template v-else>
                <span v-if="!supplier" class="text-sm text-amber-700">{{ ctrans("Not matched to a supplier yet") }}</span>
                <div v-if="assign" class="ml-auto flex items-center gap-2">
                    <Select v-model="chosenSupplier" :options="assign.options" optionLabel="label" optionValue="value" filter :placeholder="ctrans('Choose supplier')" class="w-72" size="small" />
                    <Button :label="ctrans('Assign')" type="primary" size="xs" :loading="isAssigning" :disabled="!chosenSupplier" @click="assignSupplier" />
                    <Button v-if="isReassigning" :label="ctrans('Cancel')" type="tertiary" size="xs" @click="isReassigning = false" />
                </div>
            </template>
        </div>

        <article v-for="message in messages" :key="message.id" class="rounded-md border bg-white" :class="message.is_outbound ? 'border-gray-200' : 'border-indigo-200'">
            <header class="flex flex-wrap items-start gap-x-3 gap-y-1 border-b border-gray-100 px-4 py-3">
                <FontAwesomeIcon :icon="message.is_outbound ? faPaperPlane : faInboxIn" :class="message.is_outbound ? 'text-gray-400' : 'text-indigo-500'" class="mt-0.5" fixed-width />
                <div class="min-w-0 flex-1 text-sm">
                    <div class="font-medium text-gray-800">
                        {{ message.from.name || message.from.address }}
                        <span v-if="message.from.name" class="font-normal text-gray-500">&lt;{{ message.from.address }}&gt;</span>
                    </div>
                    <div class="text-xs text-gray-500">{{ ctrans("To") }}: {{ addressList(message.to) }}</div>
                    <div v-if="message.cc.length" class="text-xs text-gray-500">{{ ctrans("Cc") }}: {{ addressList(message.cc) }}</div>
                </div>
                <div class="text-right">
                    <div class="whitespace-nowrap text-xs text-gray-500">{{ useFormatTime(message.sent_at, { formatTime: "hm" }) }}</div>
                    <Link v-if="message.purchase_order" :href="route(message.purchase_order.route.name, message.purchase_order.route.parameters)" class="primaryLink text-xs">
                        {{ message.purchase_order.reference }}
                    </Link>
                    <div v-if="message.delivery" class="text-xs text-gray-500">
                        <span class="rounded bg-gray-100 px-1.5 py-0.5 capitalize">{{ message.delivery.state.replace(/_/g, " ") }}</span>
                        <span v-if="message.delivery.reads"> · {{ ctrans("Opened") }} {{ message.delivery.reads }}×</span>
                        <span v-if="message.delivery.clicks"> · {{ ctrans("Clicked") }} {{ message.delivery.clicks }}×</span>
                    </div>
                </div>
            </header>

            <div class="px-4 py-3">
                <EmailBody v-if="formatted[message.id] && message.body_html" :html="message.body_html" />
                <div v-else class="whitespace-pre-line text-sm text-gray-700">{{ message.body_text }}</div>
                <button v-if="message.body_html" type="button" class="mt-2 text-xs text-gray-500 underline" @click="formatted[message.id] = !formatted[message.id]">
                    {{ formatted[message.id] ? ctrans("Show text only") : ctrans("Show full email") }}
                </button>
            </div>

            <footer v-if="message.attachments.length" class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3">
                <a v-for="attachment in message.attachments" :key="attachment.url" :href="attachment.url" target="_blank"
                    class="inline-flex items-center gap-1.5 rounded border border-gray-200 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                    <FontAwesomeIcon :icon="faPaperclip" class="text-gray-400" fixed-width />
                    {{ attachment.name }}
                    <span class="text-gray-400">{{ fileSize(attachment.size) }}</span>
                </a>
            </footer>
        </article>
    </div>
</template>
