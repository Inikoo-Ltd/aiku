<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt } from "@far"
import { computed, ref } from "vue"

library.add(faTrashAlt)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    batch_code: {
        id: number
        code: string
        expiry_date: string | null
        org_stock_id: number | null
        org_stock_code: string | null
        org_stock_name: string | null
    }
    delivery_notes?: object
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, unknown> = {
        delivery_notes: TableDeliveryNotes,
    }
    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-delete-batch-code>
            <ModalConfirmationDelete
                :routeDelete="{
                    name: 'grp.models.batch_code.delete',
                    parameters: [batch_code.id],
                }"
                :title="`Delete batch code &quot;${batch_code.code}&quot;?`"
            >
                <template #default="{ changeModel }">
                    <Button :style="'delete'" :icon="['far', 'fa-trash-alt']" @click="changeModel" />
                </template>
            </ModalConfirmationDelete>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <div v-if="currentTab === 'overview'" class="mt-6 px-4 max-w-2xl">
        <dl class="divide-y divide-gray-100">
            <div class="py-3 flex gap-x-4">
                <dt class="w-40 text-sm font-medium text-gray-500">{{ $t('Code') }}</dt>
                <dd class="text-sm text-gray-900">{{ batch_code.code }}</dd>
            </div>
            <div class="py-3 flex gap-x-4">
                <dt class="w-40 text-sm font-medium text-gray-500">{{ $t('Expiry Date') }}</dt>
                <dd class="text-sm text-gray-900">{{ batch_code.expiry_date ?? '—' }}</dd>
            </div>
            <div v-if="batch_code.org_stock_code" class="py-3 flex gap-x-4">
                <dt class="w-40 text-sm font-medium text-gray-500">{{ $t('SKU') }}</dt>
                <dd class="text-sm text-gray-900">
                    {{ batch_code.org_stock_code }}
                    <span v-if="batch_code.org_stock_name" class="text-gray-500 ml-1">— {{ batch_code.org_stock_name }}</span>
                </dd>
            </div>
        </dl>
    </div>

    <component
        v-else
        :is="component"
        :data="props[currentTab]"
        :tab="currentTab"
    />
</template>
