<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableArtefacts from "@/Components/Tables/Grp/Org/Production/TableArtefacts.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { computed, ref } from "vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { routeType } from "@/types/route"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    artefacts?: object
    history?: object
    move_to_family?: object
    department?: { code: string; name: string; route: routeType }
    number_artefacts: number
    delete_route?: routeType
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => ({
    artefacts: TableArtefacts,
    history: TableHistories,
}[currentTab.value]))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="flex flex-wrap items-center gap-x-4 px-4 py-2 text-sm text-gray-500">
        <span v-if="department">
            {{ ctrans('Department') }}:
            <Link :href="route(department.route.name, department.route.parameters)" class="secondaryLink">{{ department.name }}</Link>
        </span>

        <ModalConfirmationDelete
            v-if="delete_route"
            class="ml-auto"
            :routeDelete="delete_route"
            :title="ctrans('Delete this family?')"
            :description="ctrans('The family is deleted for good. This cannot be undone.')"
            :noLabel="ctrans('Yes, delete the family')">
            <template #default="{ changeModel }">
                <Button type="negative" size="xs" icon="far fa-trash-alt" :label="ctrans('Delete family')" @click="changeModel" />
            </template>

            <template #warning>
                <div v-if="number_artefacts" class="mt-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    <div class="font-semibold">
                        {{ number_artefacts === 1
                            ? ctrans('1 artefact will be left without a family')
                            : ctrans(':count artefacts will be left without a family', { count: number_artefacts }) }}
                    </div>
                    <div class="mt-1">
                        {{ ctrans('They stay in the department, you will have to put them in another family yourself.') }}
                    </div>
                </div>
            </template>
        </ModalConfirmationDelete>
    </div>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :moveToFamily="move_to_family" />
</template>
