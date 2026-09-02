<script setup lang="ts">
import { computed, ref, watch } from "vue"
import type { Component } from "vue"
import { Head } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import MailshotJourney from "@/Components/Navigation/MailshotJourney.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import WhatsappCampaignShowcase from "@/Components/Showcases/Org/WhatsappCampaign/WhatsappCampaignShowcase.vue"
import TableWhatsappCampaignRecipients from "@/Components/Tables/TableWhatsappCampaignRecipients.vue"
import { useTabChange } from "@/Composables/tab-change"
import { Tabs as TSTabs } from "@/types/Tabs"
import { routeType } from "@/types/route"

const props = defineProps<{
    title: string
    pageHead: any
    journey: any
    tabs: TSTabs
    campaign: {
        name: string
        state: string
        state_label: string
        recipients_count: number
        scheduled_at: string | null
        sent_at: string | null
    }
    status: string
    template: {
        label: string
        language: string
        header: { format?: string; text?: string | null } | null
        body: string | null
        footer: string | null
        buttons: { type?: string; text?: string }[]
    } | null
    workshopRoute: routeType
    sendRoute: routeType
    scheduleRoute: routeType
    cancelScheduleRoute: routeType
    deleteRoute: routeType
    isDeletable: boolean
    isConfigured: boolean
    timeZoneOptions: { label: string; value: string }[]
    defaultShopTimezone: string
    mergeTags: { value: string }[]
    businessName: string
    recipients: object | null
    inboxRoute: routeType
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

// Nothing has been sent yet, so there is no recipient list to look at.
const TAB_HIDE_RULES: Record<string, string[]> = {
    in_process: ["recipients"],
    ready: ["recipients"],
    scheduled: ["recipients"],
}

const filteredTabs = computed(() => {
    const hiddenTabs = TAB_HIDE_RULES[props.status ?? ""] ?? []

    return Object.fromEntries(
        Object.entries(props.tabs.navigation).filter(([key]) => !hiddenTabs.includes(key))
    )
})

const component = computed(() => {
    const components: Component = {
        showcase: WhatsappCampaignShowcase,
        recipients: TableWhatsappCampaignRecipients,
    }

    return components[currentTab.value]
})

const showcaseProps = computed(() => ({
    campaign: props.campaign,
    status: props.status,
    template: props.template,
    workshopRoute: props.workshopRoute,
    sendRoute: props.sendRoute,
    scheduleRoute: props.scheduleRoute,
    cancelScheduleRoute: props.cancelScheduleRoute,
    isConfigured: props.isConfigured,
    timeZoneOptions: props.timeZoneOptions,
    defaultShopTimezone: props.defaultShopTimezone,
    mergeTags: props.mergeTags,
    businessName: props.businessName,
}))

// Sending a campaign hides the tab it was started from, which would otherwise
// leave the page rendering nothing at all.
watch(filteredTabs, (tabs) => {
    if (!tabs[currentTab.value]) {
        currentTab.value = Object.keys(tabs)[0]
    }
})
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead">
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

    <Tabs :current="currentTab" :navigation="filteredTabs" @update:tab="handleTabUpdate" />

    <component
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
        v-bind="currentTab === 'showcase' ? showcaseProps : { inboxRoute }" />
</template>
