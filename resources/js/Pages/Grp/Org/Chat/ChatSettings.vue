<!--
  - Author: Andi Ferdiawan <dev@aw-advantage.com>
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3"
import { computed, onMounted } from "vue"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Table from "@/Components/Table/Table.vue"
import AgentsTable from "@/Components/Chat/AgentsTable.vue"
import WhatsappTemplatesTable from "@/Components/Chat/WhatsappTemplatesTable.vue"
import { useCurrentTab, useTabChange } from "@/Composables/tab-change"
import { useChatLanguages } from "@/Composables/useLanguages"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faHeadset, faMoon, faSlidersH, faThumbsUp, faTruck, faBook, faSignature, faPlus, faTrashAlt, faCheck } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"

library.add(faExternalLink, faHeadset, faMoon, faSlidersH, faThumbsUp, faTruck, faWhatsapp, faBook, faSignature)

const props = defineProps<{
    title: string
    pageHead: any
    tabs: {
        current: string
        navigation: any
    }
    templatesTable: {
        mergeTags: { name: string; value: string; example: string; group: string }[]
        variablesRoute: { name: string; parameters: Record<string, any> }
        editRouteName: string
        deleteRouteName: string
        refreshRouteName: string
        draftRouteName: string
        languageRouteName: string
        routeParameters: Record<string, any>
        canEdit: boolean
    } | null
    outOfHours: {
        message: string
        opening_line: string
        show_opening_line: boolean
        update_route: { name: string; parameters: Record<string, any> }
    } | null
    emailFooters: {
        footers: { language: string | null; text: string }[]
        can_edit: boolean
        update_route: { name: string; parameters: Record<string, any> }
    } | null
    closing: {
        close_after_thanks: boolean
        wait_for_customer_hours: number
        can_edit: boolean
        update_route: { name: string; parameters: Record<string, any> }
    } | null
    policies: {
        text: string
        notes: { id: number; title: string; body: string; updated_at: string }[]
        copied: { kind: string; total: number; at: string | null }[]
        learned: { id: number; title: string; body: string; status: "active" | "proposed" | "conflict"; conflict: string | null; customers_count: number; last_seen_at: string | null; expires_at: string | null }[]
        knowledge_route: Record<string, any>
        can_edit: boolean
        update_route: { name: string; parameters: Record<string, any> }
    } | null
    couriers: {
        domains: { domain: string; sessions: number }[]
        can_edit: boolean
        update_route: { name: string; parameters: Record<string, any> }
    } | null
    agents?: any
    whatsapp_templates?: any
}>()

const currentTab = useCurrentTab(props.tabs.current)

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab, ["pageHead"])

const shopTemplatesRoute = (shop: { organisation_slug: string; slug: string }) =>
    route("grp.org.shops.show.chat.settings", [shop.organisation_slug, shop.slug]) + "?tab=whatsapp_templates"

const outOfHoursForm = useForm({
    message: props.outOfHours?.message ?? "",
    opening_line: props.outOfHours?.show_opening_line ?? true,
})

const outOfHoursPreview = computed(() => {
    const message = outOfHoursForm.message.trim()

    return [outOfHoursForm.opening_line || !message ? props.outOfHours?.opening_line : null, message].filter(Boolean).join("\n\n")
})

const saveOutOfHours = () => {
    if (!props.outOfHours) {
        return
    }

    outOfHoursForm.patch(route(props.outOfHours.update_route.name, props.outOfHours.update_route.parameters), {
        preserveScroll: true,
        onSuccess: () => outOfHoursForm.defaults(),
    })
}

const EVERY_OTHER_LANGUAGE = "default"

const emailFootersForm = useForm({
    footers: props.emailFooters?.footers.length
        ? props.emailFooters.footers.map((footer) => ({ language: footer.language ?? EVERY_OTHER_LANGUAGE, text: footer.text }))
        : [{ language: EVERY_OTHER_LANGUAGE, text: "" }],
})

const { languages, fetchLanguages, isLoadingLanguages } = useChatLanguages("")

const languagesByName = computed(() => [...languages.value].sort((a, b) => a.name.localeCompare(b.name)))

onMounted(() => {
    if (props.emailFooters) {
        fetchLanguages()
    }
})

const footerLanguageOptions = (current: string) => [
    { value: EVERY_OTHER_LANGUAGE, label: ctrans("Every other language") },
    ...languagesByName.value
        .filter((language) => language.code === current || !emailFootersForm.footers.some((footer) => footer.language === language.code))
        .map((language) => ({ value: language.code, label: language.name })),
]

const addEmailFooter = () => {
    emailFootersForm.footers.push({ language: languagesByName.value.find((language) => !emailFootersForm.footers.some((footer) => footer.language === language.code))?.code ?? EVERY_OTHER_LANGUAGE, text: "" })
}

const removeEmailFooter = (index: number) => {
    emailFootersForm.footers.splice(index, 1)
}

const saveEmailFooters = () => {
    if (!props.emailFooters) {
        return
    }

    emailFootersForm.patch(route(props.emailFooters.update_route.name, props.emailFooters.update_route.parameters), {
        preserveScroll: true,
        onSuccess: () => emailFootersForm.defaults(),
    })
}

const closingForm = useForm({
    close_after_thanks: props.closing?.close_after_thanks ?? true,
    wait_for_customer_hours: props.closing?.wait_for_customer_hours ?? 72,
})

const saveClosing = () => {
    if (!props.closing) {
        return
    }

    closingForm.patch(route(props.closing.update_route.name, props.closing.update_route.parameters), {
        preserveScroll: true,
        onSuccess: () => closingForm.defaults(),
    })
}

const noteForm = useForm({ id: null as number | null, title: "", body: "" })

const editNote = (note: { id: number; title: string; body: string } | null) => {
    noteForm.clearErrors()
    noteForm.id = note?.id ?? null
    noteForm.title = note?.title ?? ""
    noteForm.body = note?.body ?? ""
}

const saveNote = () => {
    if (!props.policies) {
        return
    }

    const options = { preserveScroll: true, onSuccess: () => editNote(null) }

    noteForm.id
        ? noteForm.patch(route("grp.org.shops.show.chat.settings.knowledge.update", { ...props.policies.knowledge_route, chatKnowledgeEntry: noteForm.id }), options)
        : noteForm.post(route("grp.org.shops.show.chat.settings.knowledge.store", props.policies.knowledge_route), options)
}

const deleteNote = (id: number) => {
    if (!props.policies || !window.confirm(ctrans("Remove this note?"))) {
        return
    }

    noteForm.delete(route("grp.org.shops.show.chat.settings.knowledge.delete", { ...props.policies.knowledge_route, chatKnowledgeEntry: id }), { preserveScroll: true })
}

const decideLearned = (id: number, status: "active" | "removed") => {
    if (!props.policies) {
        return
    }

    noteForm.transform(() => ({ status })).patch(route("grp.org.shops.show.chat.settings.knowledge.status", { ...props.policies.knowledge_route, chatKnowledgeEntry: id }), {
        preserveScroll: true,
        onFinish: () => noteForm.transform((data) => data),
    })
}

const learnedInUse = computed(() => props.policies?.learned.filter((entry) => entry.status === "active") ?? [])
const learnedInConflict = computed(() => props.policies?.learned.filter((entry) => entry.status === "conflict") ?? [])
const learnedProposed = computed(() => props.policies?.learned.filter((entry) => entry.status === "proposed") ?? [])

const COPIED_LABELS: Record<string, string> = {
    policy: ctrans("sections of the returns, delivery and terms pages"),
    shop_fact: ctrans("facts from the shop's settings"),
    guide: ctrans("help guides"),
}

const couriersForm = useForm({
    domains: props.couriers?.domains.map((row) => row.domain).join("\n") ?? "",
})

const saveCouriers = () => {
    if (!props.couriers) {
        return
    }

    couriersForm.patch(route(props.couriers.update_route.name, props.couriers.update_route.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            couriersForm.domains = props.couriers?.domains.map((row) => row.domain).join("\n") ?? ""
            couriersForm.defaults()
        },
    })
}
</script>

<template>
    <Head :title="ctrans('Chat settings')" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <AgentsTable
        v-if="currentTab === 'agents'"
        :data="agents"
        name="agents" />

    <div v-else-if="currentTab === 'out_of_hours' && outOfHours" class="max-w-3xl space-y-5 p-6">
        <p class="text-sm text-gray-500">
            {{ ctrans("Emailed once to a customer who writes while the shop is closed. It starts with a line saying when we open again, and ends with your text. Leave it empty to send only that line.") }}
        </p>

        <div>
            <label for="out-of-hours-message" class="block text-sm font-medium text-gray-700">{{ ctrans("Your text") }}</label>
            <Textarea
                id="out-of-hours-message"
                v-model="outOfHoursForm.message"
                rows="8"
                autoResize
                class="mt-1 w-full"
                :placeholder="ctrans('For example our office hours, and how fast urgent emails are answered')" />
            <p v-if="outOfHoursForm.errors.message" class="mt-1 text-sm text-red-600">{{ outOfHoursForm.errors.message }}</p>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox v-model="outOfHoursForm.opening_line" inputId="out-of-hours-opening-line" binary />
            <label for="out-of-hours-opening-line" class="text-sm text-gray-700">{{ ctrans("Start with the line saying when we open again") }}</label>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-700">{{ ctrans("Example of the email") }}</div>
            <div class="mt-1 whitespace-pre-line rounded border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">{{ outOfHoursPreview }}</div>
        </div>

        <Button
            :label="ctrans('Save')"
            :loading="outOfHoursForm.processing"
            :disabled="!outOfHoursForm.isDirty"
            @click="saveOutOfHours" />
    </div>

    <div v-else-if="currentTab === 'email_footer' && emailFooters" class="max-w-3xl space-y-5 p-6">
        <p class="text-sm text-gray-500">
            {{ ctrans("Added under every email customer service sends from this shop, after the agent's own signature, in the language the email goes out in. The footer for every other language is used when a language has none of its own.") }}
        </p>

        <div v-for="(footer, index) in emailFootersForm.footers" :key="index" class="space-y-2 rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-2">
                <label :for="`email-footer-language-${index}`" class="text-sm font-medium text-gray-700">{{ ctrans("Language") }}</label>
                <Select v-model="footer.language" :input-id="`email-footer-language-${index}`" :options="footerLanguageOptions(footer.language)"
                    option-label="label" option-value="value" filter size="small" class="min-w-56" :disabled="!emailFooters.can_edit" />
                <span class="flex-1" />
                <button v-if="emailFooters.can_edit" type="button" v-tooltip="ctrans('Remove this footer')" :aria-label="ctrans('Remove this footer')"
                    class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-red-600"
                    @click="removeEmailFooter(index)">
                    <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                </button>
            </div>
            <Textarea v-model="footer.text" rows="4" autoResize class="w-full" :disabled="!emailFooters.can_edit"
                :aria-label="ctrans('Footer text')"
                :placeholder="ctrans('For example the shop name, address, phone number and opening hours')" />
            <p v-if="(emailFootersForm.errors as Record<string, string>)[`footers.${index}.language`]" class="text-sm text-red-600">
                {{ (emailFootersForm.errors as Record<string, string>)[`footers.${index}.language`] }}
            </p>
        </div>

        <div v-if="emailFooters.can_edit" class="flex items-center gap-2">
            <Button type="tertiary" :icon="faPlus" :label="ctrans('Add a language')" :loading="isLoadingLanguages"
                :disabled="!languages.length || emailFootersForm.footers.length > languages.length" @click="addEmailFooter" />
            <Button type="tertiary" :label="ctrans('Save')" :loading="emailFootersForm.processing" :disabled="!emailFootersForm.isDirty" @click="saveEmailFooters">
                <template #icon>
                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </div>

    <div v-else-if="currentTab === 'closing' && closing" class="max-w-3xl space-y-5 p-6">
        <p class="text-sm text-gray-500">
            {{ ctrans("An email that only thanks us after we answered gets no reply and stays open as waiting for the customer, then closes if they write nothing. Anything the customer writes next reopens it. Website chat and WhatsApp are never closed automatically: only the agent closes them.") }}
        </p>

        <div class="flex items-center gap-2">
            <Checkbox v-model="closingForm.close_after_thanks" inputId="close-after-thanks" binary :disabled="!closing.can_edit" />
            <label for="close-after-thanks" class="text-sm text-gray-700">{{ ctrans("Wait for the customer when an email only thanks us") }}</label>
        </div>

        <div>
            <label for="wait-for-customer-hours" class="block text-sm font-medium text-gray-700">{{ ctrans("Hours an email that only thanks us waits for the customer before it closes") }}</label>
            <InputNumber
                v-model="closingForm.wait_for_customer_hours"
                inputId="wait-for-customer-hours"
                :min="1"
                :max="720"
                showButtons
                :disabled="!closing.can_edit || !closingForm.close_after_thanks"
                class="mt-1" />
            <p v-if="closingForm.errors.wait_for_customer_hours" class="mt-1 text-sm text-red-600">{{ closingForm.errors.wait_for_customer_hours }}</p>
        </div>

        <p v-if="!closing.can_edit" class="text-sm text-gray-500">{{ ctrans("Only a chat supervisor can change this.") }}</p>

        <Button
            v-if="closing.can_edit"
            :label="ctrans('Save')"
            :loading="closingForm.processing"
            :disabled="!closingForm.isDirty"
            @click="saveClosing" />
    </div>

    <div v-else-if="currentTab === 'policies' && policies" class="max-w-3xl space-y-5 p-6">
        <p class="text-sm text-gray-500">
            {{ ctrans("What the AI answers customers from. Pages and shop settings are copied in every night. Add a note for anything that is on no page, like a country we cannot ship to or a product that is not available for now; notes win when they disagree with a page.") }}
        </p>

        <div v-if="policies.can_edit" class="rounded-lg border border-gray-200 p-4">
            <div class="text-sm font-medium text-gray-700">{{ noteForm.id ? ctrans("Edit note") : ctrans("New note") }}</div>
            <input v-model="noteForm.title" type="text" maxlength="200" :placeholder="ctrans('Title, for example: Shipping to Germany')"
                class="mt-2 w-full rounded-md border-gray-300 text-sm" />
            <p v-if="noteForm.errors.title" class="mt-1 text-sm text-red-600">{{ noteForm.errors.title }}</p>
            <Textarea v-model="noteForm.body" rows="4" autoResize maxlength="2000" class="mt-2 w-full text-sm"
                :placeholder="ctrans('What customer service knows, as you would tell a new colleague.')" />
            <p v-if="noteForm.errors.body" class="mt-1 text-sm text-red-600">{{ noteForm.errors.body }}</p>
            <div class="mt-2 flex gap-2">
                <Button :label="ctrans('Save note')" :loading="noteForm.processing" :disabled="!noteForm.title.trim() || !noteForm.body.trim()" @click="saveNote" />
                <Button v-if="noteForm.id" type="tertiary" :label="ctrans('Cancel')" @click="editNote(null)" />
            </div>
        </div>

        <div v-if="policies.notes.length" class="divide-y divide-gray-100 rounded-lg border border-gray-200">
            <div v-for="note in policies.notes" :key="note.id" class="flex items-start gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-800">{{ note.title }}</div>
                    <p class="whitespace-pre-line text-sm text-gray-600">{{ note.body }}</p>
                </div>
                <button v-if="policies.can_edit" type="button" class="text-xs font-medium text-gray-700 hover:underline" @click="editNote(note)">{{ ctrans("Edit") }}</button>
                <button v-if="policies.can_edit" type="button" class="text-xs text-red-600 underline" @click="deleteNote(note.id)">{{ ctrans("Remove") }}</button>
            </div>
        </div>

        <div v-if="learnedProposed.length" class="rounded-lg border border-gray-200 bg-gray-50">
            <div class="px-4 pt-3 text-sm font-medium text-gray-900">{{ ctrans("Agents' answers the AI could use") }}</div>
            <p class="px-4 text-xs text-gray-700">{{ ctrans("Several customers were told this and nothing we hold says otherwise. Check it is right before the AI uses it.") }}</p>
            <div v-for="entry in learnedProposed" :key="entry.id" class="flex items-start gap-3 border-t border-gray-100 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-800">{{ entry.title }} <span class="text-xs font-normal text-gray-500">· {{ ctrans(":count customers", { count: entry.customers_count }) }}</span></div>
                    <p class="text-sm text-gray-600">{{ entry.body }}</p>
                </div>
                <button v-if="policies.can_edit" type="button" class="text-xs text-emerald-700 underline" @click="decideLearned(entry.id, 'active')">{{ ctrans("Use it") }}</button>
                <button v-if="policies.can_edit" type="button" class="text-xs text-red-600 underline" @click="decideLearned(entry.id, 'removed')">{{ ctrans("Remove") }}</button>
            </div>
        </div>

        <div v-if="learnedInConflict.length" class="rounded-lg border border-amber-200 bg-amber-50/60">
            <div class="px-4 pt-3 text-sm font-medium text-amber-900">{{ ctrans("Agents' answers that contradict what we hold") }}</div>
            <p class="px-4 text-xs text-amber-800">{{ ctrans("Several customers were told this, but a page, a setting or a note says otherwise. Decide which is right; until then the AI does not use it.") }}</p>
            <div v-for="entry in learnedInConflict" :key="entry.id" class="flex items-start gap-3 border-t border-amber-100 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-800">{{ entry.title }} <span class="text-xs font-normal text-gray-500">· {{ ctrans(":count customers", { count: entry.customers_count }) }}</span></div>
                    <p class="text-sm text-gray-600">{{ entry.body }}</p>
                    <p v-if="entry.conflict" class="text-xs text-amber-800">{{ ctrans("Differs from:") }} {{ entry.conflict }}</p>
                </div>
                <button v-if="policies.can_edit" type="button" class="text-xs text-emerald-700 underline" @click="decideLearned(entry.id, 'active')">{{ ctrans("Use it") }}</button>
                <button v-if="policies.can_edit" type="button" class="text-xs text-red-600 underline" @click="decideLearned(entry.id, 'removed')">{{ ctrans("Remove") }}</button>
            </div>
        </div>

        <div v-if="learnedInUse.length" class="rounded-lg border border-gray-200">
            <div class="px-4 pt-3 text-sm font-medium text-gray-700">{{ ctrans("Learned from what agents told customers") }}</div>
            <div v-for="entry in learnedInUse" :key="entry.id" class="flex items-start gap-3 border-t border-gray-100 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-800">{{ entry.title }}
                        <span class="text-xs font-normal text-gray-500">· {{ ctrans(":count customers", { count: entry.customers_count }) }}<span v-if="entry.expires_at"> · {{ ctrans("until") }} {{ entry.expires_at.slice(0, 10) }}</span></span>
                    </div>
                    <p class="text-sm text-gray-600">{{ entry.body }}</p>
                </div>
                <button v-if="policies.can_edit" type="button" class="text-xs text-red-600 underline" @click="decideLearned(entry.id, 'removed')">{{ ctrans("Remove") }}</button>
            </div>
        </div>

        <div v-if="policies.copied.length" class="text-xs text-gray-500">
            {{ ctrans("Copied in automatically:") }}
            <span v-for="row in policies.copied" :key="row.kind" class="ml-2">{{ row.total }} {{ COPIED_LABELS[row.kind] ?? row.kind }}</span>
        </div>
    </div>

    <div v-else-if="currentTab === 'couriers' && couriers" class="max-w-4xl space-y-5 p-6">
        <p class="text-sm text-gray-500">
            {{ ctrans("Emails from these domains, or from any of their subdomains, go to the Couriers folder of the inbox instead of the customers' queue. One domain per line, for example gls-spain.es.") }}
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="courier-domains" class="block text-sm font-medium text-gray-700">{{ ctrans("Courier email domains") }}</label>
                <Textarea
                    id="courier-domains"
                    v-model="couriersForm.domains"
                    rows="14"
                    autoResize
                    :disabled="!couriers.can_edit"
                    class="mt-1 w-full font-mono text-sm"
                    placeholder="gls-spain.es" />
                <p v-if="couriersForm.errors.domains" class="mt-1 text-sm text-red-600">{{ couriersForm.errors.domains }}</p>
                <p v-if="!couriers.can_edit" class="mt-1 text-sm text-gray-500">{{ ctrans("Only a chat supervisor can change the list.") }}</p>
            </div>

            <div>
                <div class="text-sm font-medium text-gray-700">{{ ctrans("Open conversations filed in the last 30 days") }}</div>
                <table class="mt-1 w-full text-sm">
                    <tbody>
                        <tr v-for="row in couriers.domains" :key="row.domain" class="border-b border-gray-100">
                            <td class="py-1 font-mono">{{ row.domain }}</td>
                            <td class="py-1 text-right tabular-nums" :class="row.sessions ? 'text-gray-900' : 'text-gray-400'">{{ row.sessions }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Button
            v-if="couriers.can_edit"
            :label="ctrans('Save')"
            :loading="couriersForm.processing"
            :disabled="!couriersForm.isDirty"
            @click="saveCouriers" />
    </div>

    <template v-else-if="currentTab === 'whatsapp_templates'">
        <WhatsappTemplatesTable
            v-if="templatesTable"
            :data="whatsapp_templates"
            name="whatsapp_templates"
            :mergeTags="templatesTable.mergeTags"
            :variablesRoute="templatesTable.variablesRoute"
            :editRouteName="templatesTable.editRouteName"
            :deleteRouteName="templatesTable.deleteRouteName"
            :refreshRouteName="templatesTable.refreshRouteName"
            :draftRouteName="templatesTable.draftRouteName"
            :languageRouteName="templatesTable.languageRouteName"
            :routeParameters="templatesTable.routeParameters"
            :canEdit="templatesTable.canEdit" />

        <Table
            v-else
            :resource="whatsapp_templates"
            name="whatsapp_templates">
            <template #cell(shop)="{ item }">
                <Link
                    v-if="item.shop"
                    :href="shopTemplatesRoute(item.shop)"
                    class="primaryLink inline-flex items-center gap-1.5 text-sm">
                    {{ item.shop.name }}
                    <FontAwesomeIcon :icon="['fal', 'external-link']" class="text-[10px]" fixed-width aria-hidden="true" />
                </Link>
                <span v-else class="text-sm text-gray-400">-</span>
            </template>

            <template #cell(name)="{ item }">
                <div class="flex flex-col">
                    <span class="font-medium">{{ item.label || item.name }}</span>
                    <span v-if="item.label" class="text-[11px] text-gray-400">{{ item.name }}</span>
                </div>
            </template>

            <template #cell(status)="{ item }">
                <span
                    class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                    :class="{
                        'bg-green-100 text-green-800': item.status === 'APPROVED',
                        'bg-amber-100 text-amber-800': item.status === 'PENDING',
                        'bg-red-100 text-red-800': item.status === 'REJECTED',
                        'bg-gray-100 text-gray-700': !['APPROVED', 'PENDING', 'REJECTED'].includes(item.status),
                    }">
                    {{ item.is_draft ? ctrans("Draft") : item.status }}
                </span>
            </template>

            <template #cell(variables)="{ item }">
                <span v-if="!item.variable_count" class="text-sm text-gray-400">—</span>
                <span
                    v-else-if="item.merge_tags?.length"
                    class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                    {{ ctrans("Auto-filled") }}
                </span>
                <span v-else class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                    {{ ctrans(":count to map", { count: item.variable_count }) }}
                </span>
            </template>

            <template #cell(actions)="{ item }">
                <Link
                    v-if="item.shop"
                    :href="shopTemplatesRoute(item.shop)"
                    class="primaryLink text-xs">
                    {{ ctrans("Open in shop") }}
                </Link>
            </template>
        </Table>
    </template>
</template>
