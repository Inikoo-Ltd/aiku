<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { Message } from "primevue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableTemplateRecipients from "@/Components/Tables/TableTemplateRecipients.vue"
import { routeType } from "@/types/route"

const props = defineProps<{
    title: string
    pageHead: any
    customers: object
    selectedRecipients: string[]
    channels: Record<string, boolean>
    filters: Record<string, any>
    filtersStructure: Record<string, any>
    estimatedRecipients: number
    shop_id: number
    shop_slug: string
    updateRoute: routeType
    backRoute: routeType
}>()

const channelOptions = [
    { value: "contacted", label: "Contacted" },
    { value: "subscriber", label: "Subscriber" },
    { value: "customers", label: "Customers" },
]

const preselected = Object.fromEntries(props.selectedRecipients.map((key) => [key, true]))

// Table emits the whole map including unticked rows as false, so it is filtered on read
const selection = ref<Record<string, boolean>>({ ...preselected })
const selectedKeys = computed(() => Object.keys(selection.value).filter((key) => selection.value[key]))

const isSaving = ref(false)
const saveError = ref<string | null>(null)

const goBack = () => router.visit(route(props.backRoute.name, props.backRoute.parameters))

const onSelect = async () => {
    isSaving.value = true
    saveError.value = null

    try {
        await axios.patch(route(props.updateRoute.name, props.updateRoute.parameters), {
            recipients_recipe: {
                type: "hybrid",
                channels: props.channels,
                customer_filters: props.filters ?? {},
            },
            recipients_list: selectedKeys.value.map((phone_number) => ({ phone_number })),
        })

        goBack()
    } catch (error: any) {
        saveError.value =
            error?.response?.data?.message ?? trans("Could not save the recipients, please try again.")
        isSaving.value = false
    }
}
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead" />

    <div class="px-4 sm:px-6 py-6">
        <div class="mb-4">
            <h2 class="text-lg font-medium text-gray-800">
                {{ trans(":count contacts in your audience", { count: selectedKeys.length }) }}
            </h2>
            <p class="text-sm text-gray-500">{{ trans("Only the selected contacts will be included.") }}</p>
        </div>

        <Message v-if="saveError" severity="error" :closable="false" class="mb-4">{{ saveError }}</Message>

        <TableTemplateRecipients
            :filters="filters ?? {}"
            :filters-structure="filtersStructure"
            :recipients-recipe="filters && Object.keys(filters).length ? filters : null"
            :channels="channels"
            :channel-options="channelOptions"
            :reload-only="['customers', 'filters', 'channels', 'estimatedRecipients', 'queryBuilderProps']"
            :shop-id="shop_id"
            :shop-slug="shop_slug"
            :estimated-recipients="estimatedRecipients"
            :show-save="false"
            estimate-label="Estimated Recipients" />

        <Table
            :resource="customers"
            :isCheckBox="true"
            checkboxKey="recipient_key"
            :selectedRow="preselected"
            @onSelectRow="(rows) => (selection = rows)">
            <template #cell(sources)="{ item }">
                <div class="flex flex-wrap gap-1">
                    <span v-for="source in item.sources" :key="source"
                        class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 whitespace-nowrap">
                        {{ source }}
                    </span>
                </div>
            </template>
        </Table>

        <div class="sticky bottom-0 z-10 mt-6 flex items-center justify-between border-t border-gray-200 bg-white py-4">
            <Button :label="trans('Back')" style="tertiary" @click="goBack" />
            <Button
                :label="trans('Select')"
                style="primary"
                :loading="isSaving"
                @click="onSelect" />
        </div>
    </div>
</template>
