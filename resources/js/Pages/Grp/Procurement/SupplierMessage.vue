<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import Select from "primevue/select"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInboxIn, faPaperPlane, faPaperclip, faPersonDolly } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import EmailBody from "@/Components/Chat/EmailBody.vue"
import SupplierEmailComposer from "@/Components/Procurement/SupplierEmailComposer.vue"
import SupplierWhatsappComposer from "@/Components/Procurement/SupplierWhatsappComposer.vue"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

library.add(faWhatsapp)

interface EmailAddress {
    name: string | null
    address: string | null
}

interface MessageAttachment {
    name: string
    size: number
    mime_type: string
    url: string
    attached_to: { model_type: "purchase_order" | "stock_delivery"; reference: string; route: routeType }[]
    suggested_target: string | null
    suggested_scope: string
    attach_route: routeType
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
    attachments: MessageAttachment[]
    delivery: { state: string; reads: number; clicks: number } | null
    channel: "email" | "whatsapp" | "wechat"
    author: string | null
    purchase_order: { reference: string; route: routeType } | null
}

const props = defineProps<{
    title: string
    pageHead: object
    supplier: { type: "supplier" | "agent" | "partner"; name: string; code: string; routed_by: string | null; route: routeType } | null
    assign: { route: routeType; options: { value: string; label: string }[] } | null
    attach: { targets: { value: string; label: string }[]; scopes: { name: string; code: string }[] } | null
    reply:
        | { channel: "email"; route: routeType; to: string[]; cc: string[]; subject: string | null }
        | { channel: "whatsapp"; route: routeType; phone: string; counterpart: string | null; window_open: boolean; has_template: boolean; template: { name: string; language: string | null; status: string | null; body: string | null } | null }
        | null
    messages: ThreadMessage[]
}>()

const formatted = ref<Record<number, boolean>>({})
const chosenSupplier = ref<string | null>(null)
const isAssigning = ref(false)
const isReassigning = ref(false)

const openAttachForm = ref<string | null>(null)
const attachTarget = ref<string | null>(null)
const attachScope = ref<string | null>(null)
const isAttaching = ref(false)

const toggleAttachForm = (attachment: MessageAttachment) => {
    if (openAttachForm.value === attachment.url) {
        openAttachForm.value = null
        return
    }

    openAttachForm.value = attachment.url
    attachTarget.value = attachment.suggested_target
    attachScope.value = attachment.suggested_scope
}

const attachToOrder = (attachment: MessageAttachment) => {
    router.post(route(attachment.attach_route.name, attachment.attach_route.parameters), { target: attachTarget.value, scope: attachScope.value }, {
        preserveScroll: true,
        onStart: () => (isAttaching.value = true),
        onFinish: () => (isAttaching.value = false),
        onSuccess: () => (openAttachForm.value = null),
    })
}

const addressList = (addresses: EmailAddress[]) => addresses.map((address) => address.name || address.address).join(", ")

const fileSize = (bytes: number) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`)

const assignSupplier = () => {
    if (!props.assign || !chosenSupplier.value) {
        return
    }

    router.post(route(props.assign.route.name, props.assign.route.parameters), { counterpart: chosenSupplier.value }, {
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
                <span v-if="supplier.type !== 'supplier'" class="rounded bg-sky-50 px-1.5 py-0.5 text-xs text-sky-700">{{ supplier.type === "agent" ? ctrans("Agent") : ctrans("Partner") }}</span>
                <span v-if="supplier.routed_by === 'manual'" class="text-xs text-gray-400">· {{ ctrans("assigned by hand") }}</span>
                <Button v-if="assign" :label="ctrans('Change')" type="tertiary" size="xs" class="ml-auto" @click="isReassigning = true" />
            </template>
            <template v-else>
                <span v-if="!supplier" class="text-sm text-amber-700">{{ ctrans("Not matched to a supplier or agent yet") }}</span>
                <div v-if="assign" class="ml-auto flex items-center gap-2">
                    <Select v-model="chosenSupplier" :options="assign.options" optionLabel="label" optionValue="value" filter :placeholder="ctrans('Choose supplier or agent')" class="w-72" size="small" />
                    <Button :label="ctrans('Assign')" type="primary" size="xs" :loading="isAssigning" :disabled="!chosenSupplier" @click="assignSupplier" />
                    <Button v-if="isReassigning" :label="ctrans('Cancel')" type="tertiary" size="xs" @click="isReassigning = false" />
                </div>
            </template>
        </div>

        <article v-for="message in messages" :key="message.id" class="rounded-md border bg-white" :class="message.is_outbound ? 'border-gray-200' : 'border-indigo-200'">
            <header class="flex flex-wrap items-start gap-x-3 gap-y-1 border-b border-gray-100 px-4 py-3">
                <FontAwesomeIcon v-if="message.channel === 'whatsapp'" :icon="faWhatsapp" class="mt-0.5 text-green-600" fixed-width />
                <FontAwesomeIcon v-else :icon="message.is_outbound ? faPaperPlane : faInboxIn" :class="message.is_outbound ? 'text-gray-400' : 'text-indigo-500'" class="mt-0.5" fixed-width />
                <div class="min-w-0 flex-1 text-sm">
                    <div class="font-medium text-gray-800">
                        {{ message.from.name || message.from.address }}
                        <span v-if="message.from.name" class="font-normal text-gray-500">&lt;{{ message.from.address }}&gt;</span>
                    </div>
                    <div v-if="message.author" class="text-xs text-gray-500">{{ ctrans("Written in Aiku by") }} {{ message.author }}</div>
                    <div v-if="message.channel === 'email'" class="text-xs text-gray-500">{{ ctrans("To") }}: {{ addressList(message.to) }}</div>
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

            <footer v-if="message.attachments.length" class="space-y-2 border-t border-gray-100 px-4 py-3">
                <div v-for="attachment in message.attachments" :key="attachment.url">
                    <div class="flex flex-wrap items-center gap-2">
                        <a :href="attachment.url" target="_blank"
                            class="inline-flex items-center gap-1.5 rounded border border-gray-200 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                            <FontAwesomeIcon :icon="faPaperclip" class="text-gray-400" fixed-width />
                            {{ attachment.name }}
                            <span class="text-gray-400">{{ fileSize(attachment.size) }}</span>
                        </a>
                        <template v-if="attachment.attached_to.length">
                            <span class="text-xs text-gray-500">{{ ctrans("Attached to") }}</span>
                            <Link v-for="link in attachment.attached_to" :key="link.model_type + link.reference" :href="route(link.route.name, link.route.parameters)" class="primaryLink text-xs">
                                {{ link.reference }}
                            </Link>
                        </template>
                        <Button v-if="attach && attach.targets.length" :label="ctrans('Attach to…')" type="tertiary" size="xs" @click="toggleAttachForm(attachment)" />
                    </div>
                    <div v-if="attach && openAttachForm === attachment.url" class="mt-2 flex flex-wrap items-center gap-2">
                        <Select v-model="attachTarget" :options="attach.targets" optionLabel="label" optionValue="value" filter :placeholder="ctrans('Purchase order or stock delivery')" class="w-80" size="small" />
                        <Select v-model="attachScope" :options="attach.scopes" optionLabel="name" optionValue="code" class="w-40" size="small" />
                        <Button :label="ctrans('Attach')" type="primary" size="xs" :loading="isAttaching" :disabled="!attachTarget || !attachScope" @click="attachToOrder(attachment)" />
                    </div>
                </div>
            </footer>
        </article>

        <section v-if="reply" class="rounded-md border border-gray-200 bg-white px-4 py-3">
            <SupplierWhatsappComposer v-if="reply.channel === 'whatsapp'" :route="reply.route" :phone="reply.phone" :counterpart="reply.counterpart"
                :window-open="reply.window_open" :has-template="reply.has_template" :template="reply.template" fixed-phone />
            <SupplierEmailComposer v-else :route="reply.route" :to="reply.to" :cc="reply.cc" :subject="reply.subject" is-reply />
        </section>
    </div>
</template>
