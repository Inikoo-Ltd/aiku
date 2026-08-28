<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faUsers } from "@fortawesome/free-solid-svg-icons"
import { Message } from "primevue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import MailshotJourney from "@/Components/Navigation/MailshotJourney.vue"
import WhatsappTemplatePreview from "@/Components/Chat/WhatsappTemplatePreview.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

defineProps<{
    title: string
    pageHead: any
    journey: any
    campaign: { name: string; state: string; state_label: string; recipients_count: number }
    template: {
        label: string
        language: string
        header: { format?: string; text?: string | null } | null
        body: string | null
        footer: string | null
        buttons: { type?: string; text?: string }[]
    } | null
    workshopRoute: routeType
    mergeTags: { value: string }[]
    businessName: string
}>()
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <MailshotJourney :steps="journey" :disabledTooltip="trans('Choose a template first')" class="ml-4" />
        </template>
    </PageHeading>

    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6">
        <Message severity="info" :closable="false">
            {{ trans("Sending WhatsApp campaigns is not available yet — this page previews what will be sent.") }}
        </Message>

        <div class="flex flex-col lg:flex-row gap-6">
            <section class="flex-1 rounded-xl border border-gray-200 overflow-hidden self-start w-full">
                <header class="bg-gray-50 border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700">
                    {{ trans("Summary") }}
                </header>

                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Campaign name") }}</dt>
                        <dd class="text-gray-800 text-right">{{ campaign.name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Status") }}</dt>
                        <dd class="text-gray-800 text-right">{{ campaign.state_label }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("WhatsApp number") }}</dt>
                        <dd class="text-gray-800 text-right flex items-center gap-2 justify-end">
                            <FontAwesomeIcon :icon="faWhatsapp" class="text-green-500" />
                            {{ businessName }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Template") }}</dt>
                        <dd class="text-gray-800 text-right">
                            <template v-if="template">{{ template.label }} ({{ template.language }})</template>
                            <Link v-else :href="route(workshopRoute.name, workshopRoute.parameters)"
                                class="text-indigo-600 hover:text-indigo-700">
                                {{ trans("Choose a template") }}
                            </Link>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500 flex items-center gap-2">
                            <FontAwesomeIcon :icon="faUsers" class="text-gray-400" />
                            {{ trans("Recipients") }}
                        </dt>
                        <dd class="text-gray-800 text-right">{{ campaign.recipients_count }}</dd>
                    </div>
                </dl>

                <div class="border-t border-gray-200 px-4 py-3 flex items-center justify-end gap-2">
                    <Button
                        :label="trans('Send test message')"
                        style="tertiary"
                        disabled
                        :tooltip="trans('Sending is not available yet')" />
                    <Button
                        :label="trans('Send')"
                        style="primary"
                        disabled
                        :tooltip="trans('Sending is not available yet')" />
                </div>
            </section>

            <div class="w-full lg:w-[330px] shrink-0">
                <WhatsappTemplatePreview
                    :body="template?.body"
                    :header="template?.header"
                    :footer="template?.footer"
                    :buttons="template?.buttons ?? []"
                    :businessName="businessName"
                    :mergeTags="mergeTags"
                    :placeholder="trans('No template chosen yet…')" />
            </div>
        </div>
    </div>
</template>
