<script setup lang="ts">
import { ref } from "vue"
import { Link } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInboxIn, faPaperPlane, faPaperclip, faPen } from "@fal"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierEmailComposer from "@/Components/Procurement/SupplierEmailComposer.vue"
import SupplierWhatsappComposer from "@/Components/Procurement/SupplierWhatsappComposer.vue"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

const props = defineProps<{
    data: {
        compose?: {
            email: { route: routeType; to: string[]; counterpart: string | null } | null
            whatsapp: { route: routeType; phone: string | null; counterpart: string | null; window_open: boolean; has_template: boolean; template: { name: string; language: string | null; status: string | null; body: string | null } | null } | null
        } | null
    }
    tab?: string
}>()

const isComposing = ref(false)
const composeChannel = ref<"email" | "whatsapp">(props.data.compose?.email ? "email" : "whatsapp")

interface SupplierEmailRow {
    is_outbound: boolean
    channel: "email" | "whatsapp" | "wechat"
    sent_at: string
    counterpart_name: string | null
    counterpart_type: "supplier" | "agent" | "partner" | null
    organisation_code: string | null
    correspondent: string | null
    subject: string
    snippet: string | null
    number_attachments: number
    route: routeType
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template v-if="data.compose" #add-on-button>
            <Button :label="ctrans('New message')" :icon="faPen" type="create" size="xs" @click="isComposing = true" />
        </template>

        <template #cell(direction)="{ item }: { item: SupplierEmailRow }">
            <FontAwesomeIcon v-if="item.channel === 'whatsapp'" :icon="faWhatsapp" class="mr-1 text-green-600" fixed-width v-tooltip="ctrans('WhatsApp')" />
            <FontAwesomeIcon v-if="item.is_outbound" :icon="faPaperPlane" class="text-gray-400" fixed-width v-tooltip="ctrans('Sent')" />
            <FontAwesomeIcon v-else :icon="faInboxIn" class="text-indigo-500" fixed-width v-tooltip="ctrans('Received')" />
        </template>

        <template #cell(sent_at)="{ item }: { item: SupplierEmailRow }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.sent_at, { formatTime: "hm" }) }}</span>
        </template>

        <template #cell(counterpart_name)="{ item }: { item: SupplierEmailRow }">
            <span v-if="item.counterpart_name">
                {{ item.counterpart_name }}
                <span v-if="item.counterpart_type !== 'supplier'" class="ml-1 rounded bg-sky-50 px-1.5 py-0.5 text-xs text-sky-700">{{ item.counterpart_type === "agent" ? ctrans("Agent") : ctrans("Partner") }}</span>
            </span>
            <span v-else class="rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-700">{{ ctrans("Unassigned") }}</span>
        </template>

        <template #cell(subject)="{ item }: { item: SupplierEmailRow }">
            <Link :href="route(item.route.name, item.route.parameters)" class="primaryLink">{{ item.subject }}</Link>
            <FontAwesomeIcon v-if="item.number_attachments" :icon="faPaperclip" class="ml-1 text-gray-400" fixed-width v-tooltip="ctrans('Attachments') + ': ' + item.number_attachments" />
            <div v-if="item.snippet" class="max-w-xl truncate text-xs text-gray-500">{{ item.snippet }}</div>
        </template>
    </Table>

    <Dialog v-if="data.compose" v-model:visible="isComposing" modal :header="ctrans('New message')" :style="{ width: '44rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div v-if="data.compose.email && data.compose.whatsapp" class="mb-3 flex gap-2">
            <Button :label="ctrans('Email')" :type="composeChannel === 'email' ? 'primary' : 'tertiary'" size="xs" @click="composeChannel = 'email'" />
            <Button :label="ctrans('WhatsApp')" :icon="faWhatsapp" :type="composeChannel === 'whatsapp' ? 'primary' : 'tertiary'" size="xs" @click="composeChannel = 'whatsapp'" />
        </div>
        <SupplierEmailComposer v-if="composeChannel === 'email' && data.compose.email" :route="data.compose.email.route" :to="data.compose.email.to" :counterpart="data.compose.email.counterpart" @sent="isComposing = false" />
        <SupplierWhatsappComposer v-else-if="data.compose.whatsapp" :route="data.compose.whatsapp.route" :phone="data.compose.whatsapp.phone" :counterpart="data.compose.whatsapp.counterpart"
            :window-open="data.compose.whatsapp.window_open" :has-template="data.compose.whatsapp.has_template" :template="data.compose.whatsapp.template" @sent="isComposing = false" />
    </Dialog>
</template>
