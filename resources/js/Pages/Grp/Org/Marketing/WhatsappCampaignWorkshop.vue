<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faUsers, faBolt } from "@fortawesome/free-solid-svg-icons"
import { Message } from "primevue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import MailshotJourney from "@/Components/Navigation/MailshotJourney.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import WhatsappTemplatePreview from "@/Components/Chat/WhatsappTemplatePreview.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { routeType } from "@/types/route"

type TemplateOption = {
    value: number
    label: string
    language: string
    header: { format?: string; text?: string | null } | null
    body: string | null
    footer: string | null
    buttons: { type?: string; text?: string }[]
    mergeTags: string[]
}

const props = defineProps<{
    title: string
    pageHead: any
    journey: any
    campaign: { name: string; meta_message_template_id: number | null; recipients_count: number }
    updateRoute: routeType
    recipientsRoute: routeType
    clearRecipientsRoute: routeType
    createTemplateRoute: routeType
    templates: TemplateOption[]
    mergeTags: { value: string }[]
    businessName: string
    isConfigured: boolean
    isEditable: boolean
    isDeletable: boolean
    deleteRoute: routeType
}>()

const name = ref(props.campaign.name)
const templateId = ref<number | null>(props.campaign.meta_message_template_id)
const recipientsCount = ref(props.campaign.recipients_count)
const savedName = ref<string | null>(null)
const saveError = ref<string | null>(null)

const templateOf = (id: number | null): TemplateOption | null =>
    props.templates.find((template: TemplateOption) => template.value === id) ?? null

const selectedTemplate = computed(() => templateOf(templateId.value))

const pageHeadData = computed(() =>
    savedName.value ? { ...props.pageHead, title: savedName.value } : props.pageHead
)

const persist = async (payload: Record<string, unknown>) => {
    if (!props.isEditable) return

    saveError.value = null

    try {
        await axios.patch(route(props.updateRoute.name, props.updateRoute.parameters), payload)
    } catch (error: any) {
        saveError.value =
            error?.response?.data?.message ?? trans("Could not save the campaign, please try again.")
    }
}

const onNameBlur = async () => {
    const trimmed = name.value.trim()

    if (!trimmed || trimmed === props.campaign.name) return

    await persist({ name: trimmed })

    if (!saveError.value) savedName.value = trimmed
}

/* A template's merge tags decide who it can reach: recipients were picked against the old
   template's tags, and the send path drops any contact that cannot supply every tag of the
   new one. Compared as a set, because order only decides which {{n}} a tag lands in. */
const tagsDiffer = (a: string[] = [], b: string[] = []) => {
    const left = [...new Set(a)].sort()
    const right = [...new Set(b)].sort()

    return left.length !== right.length || left.some((tag, index) => tag !== right[index])
}

const pendingTemplateId = ref<number | null>(null)
const isConfirmingReset = ref(false)

/* The multiselect keeps its own copy of the value, so a declined change has to be undone by
   remounting it rather than by leaving templateId alone. */
const selectKey = ref(0)

/* The recipients are rows of their own now, so clearing them is a second call rather than a
   field on the campaign. Template first: a failed clear leaves a stale count next to the new
   template, which the picker corrects, where the reverse would drop an audience the user
   never agreed to lose. */
const applyTemplate = async (value: number | null, resetRecipients = false) => {
    templateId.value = value

    await persist({ meta_message_template_id: value })

    if (!resetRecipients || saveError.value) return

    try {
        await axios.post(
            route(props.clearRecipientsRoute.name, props.clearRecipientsRoute.parameters),
            { phone_keys: [] }
        )
        recipientsCount.value = 0
    } catch (error: any) {
        saveError.value =
            error?.response?.data?.message ?? trans("Could not clear the recipients, please try again.")
    }
}

const onTemplateChange = (value: number | null) => {
    if (value === templateId.value) return

    const needsConfirmation =
        templateId.value !== null &&
        recipientsCount.value > 0 &&
        tagsDiffer(selectedTemplate.value?.mergeTags, templateOf(value)?.mergeTags)

    if (!needsConfirmation) {
        applyTemplate(value)

        return
    }

    pendingTemplateId.value = value
    isConfirmingReset.value = true
}

const cancelTemplateChange = () => {
    isConfirmingReset.value = false
    pendingTemplateId.value = null
    selectKey.value++
}

const confirmTemplateChange = async () => {
    isConfirmingReset.value = false

    await applyTemplate(pendingTemplateId.value, true)

    pendingTemplateId.value = null
}
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHeadData">
        <template #afterTitle2>
            <MailshotJourney :steps="journey" :disabledTooltip="trans('Choose a template first')" class="ml-4" />
        </template>
        <template #other>
            <ModalConfirmationDelete
                v-if="isDeletable"
                :routeDelete="deleteRoute"
                :title="trans('Are you sure you want to delete this campaign?')"
                :description="trans('This campaign and its draft audience will be removed.')"
                :noLabel="trans('Delete campaign')">
                <template #default="{ changeModel }">
                    <Button
                        icon="fal fa-trash-alt"
                        style="negative"
                        :tooltip="trans('Delete campaign')"
                        @click="changeModel" />
                </template>
            </ModalConfirmationDelete>
        </template>
    </PageHeading>

    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6">
        <Message v-if="!isEditable" severity="info" :closable="false">
            {{ trans("This campaign is no longer editable, cancel its schedule to change it.") }}
        </Message>

        <Message v-if="!isConfigured" severity="warn" :closable="false">
            {{ trans("WhatsApp is not configured for this shop yet, so this campaign cannot be sent.") }}
        </Message>

        <Message v-if="saveError" severity="error" :closable="false">{{ saveError }}</Message>

        <section class="rounded-xl border border-gray-200 overflow-hidden">
            <header class="bg-gray-50 border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700">
                {{ trans("Campaign details") }}
            </header>

            <div class="p-4 max-w-md">
                <label class="block text-sm font-medium text-gray-700">{{ trans("Campaign name") }}</label>
                <p class="text-xs text-gray-500 mb-2">{{ trans("The name is only used internally") }}</p>
                <PureInput v-model="name" :disabled="!isEditable" @blur="onNameBlur" />
            </div>
        </section>

        <!-- no overflow-hidden here: it would clip the template dropdown, so the header
             rounds its own top corners instead -->
        <section class="rounded-xl border border-gray-200">
            <header class="bg-gray-50 border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 rounded-t-xl">
                {{ trans("Channel and Content") }}
            </header>

            <div class="p-4 flex flex-col lg:flex-row gap-6">
                <div class="flex-1 space-y-4 max-w-md relative z-10">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ trans("WhatsApp number") }}
                        </label>
                        <!-- ponytail: a shop has exactly one WhatsApp identity in settings, so this
                             shows it rather than offering a picker over a list that does not exist -->
                        <div class="flex items-center gap-2 rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                            <FontAwesomeIcon :icon="faWhatsapp" class="text-green-500" />
                            {{ businessName }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            {{ trans("WhatsApp template") }}
                        </label>
                        <PureMultiselect
                            :key="selectKey"
                            :modelValue="templateId"
                            @update:modelValue="onTemplateChange"
                            :disabled="!isEditable"
                            :required="true"
                            :options="templates"
                            label="label"
                            valueProp="value"
                            searchable
                            :classes="{ dropdown: 'multiselect-dropdown !z-[1000]' }"
                            :placeholder="trans('Select a template')" />
                        <p v-if="!templates.length" class="text-xs text-gray-500 mt-2">
                            {{ trans("No approved templates yet. Create one and wait for Meta to approve it.") }}
                        </p>

                        <Link
                            :href="route(createTemplateRoute.name, createTemplateRoute.parameters)"
                            class="inline-block mt-2">
                            <Button :label="trans('Create Template')" style="tertiary" size="xs" icon="fal fa-plus" />
                        </Link>
                    </div>
                </div>

                <div class="w-full lg:w-[330px] shrink-0">
                    <WhatsappTemplatePreview
                        :body="selectedTemplate?.body"
                        :header="selectedTemplate?.header"
                        :footer="selectedTemplate?.footer"
                        :buttons="selectedTemplate?.buttons ?? []"
                        :businessName="businessName"
                        :mergeTags="mergeTags"
                        :placeholder="trans('Select a template to preview it here…')" />
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 overflow-hidden">
            <header class="bg-gray-50 border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700">
                {{ trans("Recipient") }}
            </header>

            <div class="p-4 max-w-md">
                <div class="rounded-lg border border-gray-200 p-4 flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-700">
                            {{ trans(":count contacts selected", { count: recipientsCount }) }}
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ trans("Choose which contacts receive this campaign.") }}
                        </p>
                    </div>
                    <Link
                        v-if="isEditable && templateId"
                        :href="route(recipientsRoute.name, recipientsRoute.parameters)">
                        <Button :label="trans('Edit')" style="tertiary" size="xs" />
                    </Link>
                    <Button
                        v-else-if="isEditable"
                        :label="trans('Edit')"
                        style="tertiary"
                        size="xs"
                        :disabled="true"
                        v-tooltip="trans('Choose a template first')" />
                </div>

                <p v-if="isEditable && !templateId" class="mt-2 text-xs text-gray-500">
                    {{ trans("A template's merge tags decide who can be reached, so recipients are chosen after it.") }}
                </p>

                <div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                    <FontAwesomeIcon :icon="faUsers" class="text-gray-400" />
                    {{ trans("Number of recipients") }}: {{ recipientsCount }}
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 overflow-hidden opacity-60">
            <header class="bg-gray-50 border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 flex items-center gap-2">
                <FontAwesomeIcon :icon="faBolt" class="text-gray-400 text-xs" />
                {{ trans("Automation") }}
                <span class="text-xs font-normal text-gray-400">({{ trans("coming soon") }})</span>
            </header>
        </section>

        <Modal :isOpen="isConfirmingReset" width="w-full max-w-lg" @onClose="cancelTemplateChange">
            <h3 class="text-base font-medium text-gray-800">
                {{ trans("This template needs different information") }}
            </h3>
            <p class="mt-2 text-sm text-gray-500">
                {{ trans("Your :count selected contacts were chosen for the current template's merge tags. This template needs different ones, so the selection will be cleared and you will need to choose recipients again.", { count: recipientsCount }) }}
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <Button :label="trans('Cancel')" style="tertiary" @click="cancelTemplateChange" />
                <Button
                    :label="trans('Change template and clear recipients')"
                    style="primary"
                    @click="confirmTemplateChange" />
            </div>
        </Modal>
    </div>
</template>
