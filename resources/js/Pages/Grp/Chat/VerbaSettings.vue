<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { reactive } from "vue"
import axios from "axios"
import MultiSelect from "primevue/multiselect"
import ToggleSwitch from "primevue/toggleswitch"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCog, faHashtag, faArchive } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faCog, faHashtag, faArchive)

type ChannelRow = {
    id: number
    name: string | null
    workspace: string | null
    is_archived: boolean
    is_hidden_in_lists: boolean
    organisation_ids: number[]
}

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    organisations: Array<{ id: number; name: string }>
    channels: object
}>()

const saving = reactive<Record<number, boolean>>({})
const pickedOrganisations = reactive<Record<number, number[]>>({})
const saveOrganisations = (row: ChannelRow) => {
    const picked = pickedOrganisations[row.id]
    delete pickedOrganisations[row.id]
    if (picked && [...picked].sort().join() !== [...row.organisation_ids].sort().join()) {
        save(row, { organisation_ids: picked })
    }
}
const errors = reactive<Record<number, string | null>>({})

const save = async (row: ChannelRow, changes: Partial<Pick<ChannelRow, "is_hidden_in_lists" | "organisation_ids">>) => {
    const before = { is_hidden_in_lists: row.is_hidden_in_lists, organisation_ids: [...row.organisation_ids] }
    Object.assign(row, changes)
    saving[row.id] = true
    errors[row.id] = null
    try {
        const { data } = await axios.patch(route("grp.chat.staff.settings.channels.update", row.id), changes)
        Object.assign(row, data)
    } catch {
        Object.assign(row, before)
        errors[row.id] = ctrans("Not saved. Try again.")
    } finally {
        saving[row.id] = false
    }
}
</script>

<template>
    <div class="min-h-full bg-[var(--vx-soft,#f5f3f5)]">
        <Head :title="capitalize(title)" />
        <div class="verba-settings-heading bg-[var(--vx-sidebar,#3f0e40)] text-[var(--vx-sidebar-strong,#ffffff)]">
            <PageHeading :data="pageHead" />
        </div>

        <div class="space-y-3 px-4 pb-8 pt-4">
            <p class="max-w-prose text-sm text-gray-600">{{ ctrans("Choose which organisations get each public V·Desk in their list, or hide it from every list. Nothing changes in Slack, and anyone can still open a V·Desk by link or search.") }}</p>

            <Table :resource="channels" name="channels">
                <template #cell(name)="{ item }">
                    <span class="inline-flex items-center gap-x-1.5 whitespace-nowrap" :class="item.is_archived ? 'text-gray-400' : 'text-gray-800'">
                        <FontAwesomeIcon :icon="item.is_archived ? 'fal fa-archive' : 'fal fa-hashtag'" fixed-width aria-hidden="true" />
                        {{ item.name }}
                        <span v-if="item.workspace" class="rounded-sm bg-amber-400 px-[3px] text-[9px] font-bold leading-[13px] text-black" v-tooltip="ctrans(':workspace Slack (Bali)', { workspace: item.workspace })">B</span>
                    </span>
                </template>
                <template #cell(organisation_ids)="{ item }">
                    <MultiSelect
                        :model-value="pickedOrganisations[item.id] ?? item.organisation_ids"
                        :options="organisations"
                        option-label="name"
                        option-value="id"
                        display="chip"
                        :placeholder="ctrans('Everyone')"
                        :disabled="saving[item.id]"
                        :max-selected-labels="3"
                        class="w-full min-w-64 max-w-md"
                        :aria-label="ctrans('Organisations that get :channel', { channel: item.name ?? '' })"
                        @update:model-value="(ids: number[]) => (pickedOrganisations[item.id] = ids)"
                        @hide="saveOrganisations(item)" />
                    <p v-if="errors[item.id]" class="mt-1 text-xs text-red-600" role="alert">{{ errors[item.id] }}</p>
                </template>
                <template #cell(is_hidden_in_lists)="{ item }">
                    <ToggleSwitch
                        :model-value="item.is_hidden_in_lists"
                        :disabled="saving[item.id]"
                        :aria-label="ctrans('Hide :channel from every list', { channel: item.name ?? '' })"
                        @update:model-value="(hidden: boolean) => save(item, { is_hidden_in_lists: hidden })" />
                </template>
            </Table>
        </div>
    </div>
</template>

<style scoped>
.verba-settings-heading :deep(*) {
    color: inherit;
}
</style>
