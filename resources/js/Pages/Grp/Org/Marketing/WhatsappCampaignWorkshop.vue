<script setup lang="ts">
import { computed, ref, watch } from "vue"
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
import { routeType } from "@/types/route"

type TemplateOption = {
    value: number
    label: string
    language: string
    header: { format?: string; text?: string | null } | null
    body: string | null
    footer: string | null
    buttons: { type?: string; text?: string }[]
}

const props = defineProps<{
    title: string
    pageHead: any
    journey: any
    campaign: { name: string; meta_message_template_id: number | null; recipients_count: number }
    updateRoute: routeType
    recipientsRoute: routeType
    templates: TemplateOption[]
    mergeTags: { value: string }[]
    businessName: string
    isConfigured: boolean
    isEditable: boolean
}>()

const name = ref(props.campaign.name)
const templateId = ref<number | null>(props.campaign.meta_message_template_id)
const savedName = ref<string | null>(null)
const saveError = ref<string | null>(null)

const selectedTemplate = computed(
    () => props.templates.find((template) => template.value === templateId.value) ?? null
)

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

watch(templateId, (value) => persist({ meta_message_template_id: value }))
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHeadData">
        <template #afterTitle2>
            <MailshotJourney :steps="journey" :disabledTooltip="trans('Choose a template first')" class="ml-4" />
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
                            v-model="templateId"
                            :disabled="!isEditable"
                            :options="templates"
                            label="label"
                            valueProp="value"
                            searchable
                            :classes="{ dropdown: 'multiselect-dropdown !z-[1000]' }"
                            :placeholder="trans('Select a template')" />
                        <p v-if="!templates.length" class="text-xs text-gray-500 mt-2">
                            {{ trans("No approved templates yet. Create one and wait for Meta to approve it.") }}
                        </p>
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
                            {{ trans(":count contacts selected", { count: campaign.recipients_count }) }}
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ trans("Choose which contacts receive this campaign.") }}
                        </p>
                    </div>
                    <Link
                        v-if="isEditable"
                        :href="route(recipientsRoute.name, recipientsRoute.parameters)">
                        <Button :label="trans('Edit')" style="tertiary" size="xs" />
                    </Link>
                </div>

                <div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                    <FontAwesomeIcon :icon="faUsers" class="text-gray-400" />
                    {{ trans("Number of recipients") }}: {{ campaign.recipients_count }}
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
    </div>
</template>
