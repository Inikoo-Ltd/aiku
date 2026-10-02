<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import StaffTaskQuickLook from "@/Components/Tasks/StaffTaskQuickLook.vue"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFlag } from "@fal"
library.add(faFlag)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()

const quickLook = ref<any | null>(null)
const hasChangedInQuickLook = ref(false)

const onTableClick = async (event: MouseEvent) => {
    const target = event.target as HTMLElement | null
    if (!target || target.closest("a, button, input, select, textarea, label, [role='listbox'], [role='option']")) return

    const reference = target.closest("tr")?.querySelector<HTMLElement>("[data-task-reference]")?.dataset.taskReference
    const row = (props.data as any)?.data?.find((item: any) => item.reference === reference)
    if (!row) return

    hasChangedInQuickLook.value = false
    quickLook.value = {
        ...row,
        is_partial: true,
        requester: row.requester_name ? { name: row.requester_name } : null,
        assignee: row.assignee_name ? { name: row.assignee_name } : null,
        collaborators: [],
        attachments: [],
    }

    try {
        const { data } = await axios.get(route("grp.tasks.details", row.reference))
        if (quickLook.value?.reference === row.reference) quickLook.value = data.data
    } catch {
        if (quickLook.value?.reference === row.reference) quickLook.value = null
        notify({ title: ctrans("Something went wrong"), type: "error" })
    }
}

const closeQuickLook = () => {
    if (hasChangedInQuickLook.value) router.reload({ only: ["data"] })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="[&_tbody_tr]:cursor-pointer" @click="onTableClick">
    <Table :resource="data" class="mt-5">
        <template #cell(reference)="{ item }">
            <span class="font-mono" :data-task-reference="item.reference">{{ item.reference }}</span>
        </template>
        <template #cell(status)="{ item }">
            <span class="inline-flex items-center gap-1" :title="item.status_label"><Icon :data="item.status_icon" /> {{ item.status_label }}</span>
        </template>
        <template #cell(priority)="{ item }">
            <div class="flex justify-center" :title="item.priority_label"><Icon :data="item.priority_icon" /></div>
        </template>
        <template #cell(requester)="{ item }">
            {{ item.requester_name || "-" }}
        </template>
        <template #cell(assignee)="{ item }">
            {{ item.assignee_name || item.department_label || "-" }}
        </template>
        <template #cell(due_at)="{ item }">
            {{ item.due_at ? useFormatTime(item.due_at, { formatTime: "mdy" }) : "-" }}
        </template>
        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at, { formatTime: "hm" }) }}
        </template>
        <template #cell(closed_at)="{ item }">
            {{ item.closed_at ? useFormatTime(item.closed_at, { formatTime: "hm" }) : "-" }}
        </template>
    </Table>
    </div>
    <StaffTaskQuickLook v-model:task="quickLook" @updated="hasChangedInQuickLook = true" @closed="closeQuickLook" />
</template>
