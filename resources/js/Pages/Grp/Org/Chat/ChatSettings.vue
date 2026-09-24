<!--
  - Author: Andi Ferdiawan <dev@aw-advantage.com>
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3"
import { computed } from "vue"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Table from "@/Components/Table/Table.vue"
import AgentsTable from "@/Components/Chat/AgentsTable.vue"
import WhatsappTemplatesTable from "@/Components/Chat/WhatsappTemplatesTable.vue"
import { useCurrentTab, useTabChange } from "@/Composables/tab-change"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faHeadset, faMoon, faSlidersH } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"

library.add(faExternalLink, faHeadset, faMoon, faSlidersH, faWhatsapp)

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
    } | null
    outOfHours: {
        message: string
        opening_line: string
        update_route: { name: string; parameters: Record<string, any> }
    } | null
    agents?: any
    whatsapp_templates?: any
}>()

const currentTab = useCurrentTab(props.tabs.current)

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab, ["pageHead"])

const shopTemplatesRoute = (shop: { organisation_slug: string; slug: string }) =>
    route("grp.org.shops.show.chat.settings", [shop.organisation_slug, shop.slug]) + "?tab=whatsapp_templates"

const outOfHoursForm = useForm({ message: props.outOfHours?.message ?? "" })

const outOfHoursPreview = computed(() =>
    [props.outOfHours?.opening_line, outOfHoursForm.message.trim()].filter(Boolean).join("\n\n")
)

const saveOutOfHours = () => {
    if (!props.outOfHours) {
        return
    }

    outOfHoursForm.patch(route(props.outOfHours.update_route.name, props.outOfHours.update_route.parameters), {
        preserveScroll: true,
        onSuccess: () => outOfHoursForm.defaults(),
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
            :routeParameters="templatesTable.routeParameters" />

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
