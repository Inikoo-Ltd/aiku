<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, Link, useForm } from "@inertiajs/vue3"
import { Dialog, Select, MultiSelect, InputText, Textarea, DatePicker } from "primevue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketProjectProgress from "@/Components/Tickets/TicketProjectProgress.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faProjectDiagram, faPlus, faTable, faThLarge, faTimes, faCheck, faLevelUp } from "@fal"

library.add(faProjectDiagram, faPlus, faTable, faThLarge, faTimes, faCheck, faLevelUp)

type Person = { id: number; name: string; avatar: Record<string, string> | null }
type Progress = { total: number; done: number; open: number; percent: number | null; time_percent: number | null; week: number; weeks: number | null; days_left: number | null }
type ProjectRow = {
    slug: string
    name: string
    status: string
    status_label: string
    health: string | null
    health_label: string | null
    start_date: string
    target_date: string | null
    owner: Person | null
    members: Person[]
    progress: Progress
    children: ProjectRow[]
}

const props = defineProps<{
    pageHead: any
    title: string
    data: { data: ProjectRow[] }
    view: "table" | "cards"
    can_create: boolean
    staff: { label: string; value: number }[]
    parent_options: { label: string; value: number }[]
    store_route: { name: string }
}>()

const view = ref<"table" | "cards">(props.view === "cards" ? "cards" : "table")
const viewOptions = [
    { label: ctrans("Table"), value: "table" as const, icon: "fal fa-table" },
    { label: ctrans("Cards"), value: "cards" as const, icon: "fal fa-th-large" },
]
watch(view, (value) => {
    if (value) axios.patch(route("grp.models.profile.update"), { projects_view: value })
})

const projects = computed(() => props.data?.data ?? [])

const toIso = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const asDate = (value: string | null | undefined) => (value ? new Date(value.slice(0, 10) + "T00:00:00") : null)
const fromDate = (value: Date | Date[] | (Date | null)[] | null | undefined) => (value instanceof Date ? toIso(value) : null)
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const isCreating = ref(false)
const form = useForm({
    name: "",
    description: "",
    start_date: toIso(new Date()),
    target_date: "",
    owner_id: null as number | null,
    member_ids: [] as number[],
    parent_id: null as number | null,
})

const create = () => form.post(route(props.store_route.name), { onSuccess: () => (isCreating.value = false) })

const statusClasses: Record<string, string> = {
    active: "bg-green-100 text-green-700",
    on_hold: "bg-amber-100 text-amber-700",
    done: "bg-gray-100 text-gray-600",
    cancelled: "bg-red-50 text-red-600",
}

const healthClasses: Record<string, string> = {
    on_track: "bg-green-100 text-green-700",
    at_risk: "bg-amber-100 text-amber-700",
    off_track: "bg-red-100 text-red-700",
}

const isBehind = (progress: Progress) => progress.percent !== null && progress.time_percent !== null && progress.percent + 10 < progress.time_percent
const percentLabel = (value: number | null) => (value === null ? "—" : value + "%")
const timeLabel = (progress: Progress) => {
    if (progress.days_left === null) return ctrans("Week :week", { week: progress.week })
    if (progress.days_left < 0) return ctrans(":days days past target", { days: -progress.days_left })
    return ctrans(":days days left", { days: progress.days_left })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <SegmentedToggle v-model="view" :options="viewOptions" :aria-label="ctrans('View')" icon-only />
            <Button v-if="can_create" type="tertiary" :label="ctrans('New project')" @click="isCreating = true">
                <template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width /></template>
            </Button>
        </template>
    </PageHeading>

    <Table v-if="view === 'table'" :resource="data" class="mt-2" :useTopPagination="true">
        <template #cell(name)="{ item }">
            <Link :href="route('grp.projects.show', item.slug)" class="font-medium text-gray-800 hover:underline">{{ item.name }}</Link>
            <div v-if="item.children.length" class="mt-1 space-y-0.5">
                <div v-for="child in item.children" :key="child.slug" class="grid min-w-[22rem] grid-cols-[1rem_minmax(8rem,1fr)_1.25rem_4rem_6.5rem] items-center gap-2 text-xs text-gray-600">
                    <FontAwesomeIcon icon="fal fa-level-up" class="rotate-90 text-gray-400" fixed-width aria-hidden="true" />
                    <Link :href="route('grp.projects.show', child.slug)" class="hover:underline">{{ child.name }}</Link>
                    <span>
                        <TicketUserAvatar v-if="child.owner" v-tooltip="ctrans('Owner') + ': ' + child.owner.name" :name="child.owner.name" :avatar="child.owner.avatar" size="xs" />
                    </span>
                    <span class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <span class="block h-full rounded-full" :class="isBehind(child.progress) ? 'bg-red-400' : 'bg-green-500'" :style="{ width: (child.progress.percent ?? 0) + '%' }" />
                    </span>
                    <span class="text-right tabular-nums" :class="isBehind(child.progress) && 'font-semibold text-red-600'">{{ child.progress.done }} / {{ child.progress.done + child.progress.open }} · {{ percentLabel(child.progress.percent) }}</span>
                </div>
            </div>
        </template>
        <template #cell(status)="{ item }">
            <span class="flex gap-1">
                <span v-if="item.health" class="rounded-full px-2 py-0.5 text-xs" :class="healthClasses[item.health]">{{ item.health_label }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClasses[item.status]">{{ item.status_label }}</span>
            </span>
        </template>
        <template #cell(progress)="{ item }">
            <span class="grid grid-cols-[6rem_6.5rem] items-center gap-2 text-xs">
                <span class="h-2 overflow-hidden rounded-full bg-gray-100">
                    <span class="block h-full rounded-full" :class="isBehind(item.progress) ? 'bg-red-400' : 'bg-green-500'" :style="{ width: (item.progress.percent ?? 0) + '%' }" />
                </span>
                <span class="text-right tabular-nums" :class="isBehind(item.progress) ? 'font-semibold text-red-600' : 'text-gray-600'">{{ item.progress.done }} / {{ item.progress.done + item.progress.open }} · {{ percentLabel(item.progress.percent) }}</span>
            </span>
        </template>
        <template #cell(time)="{ item }">
            <span class="grid grid-cols-[6rem_3rem] items-center gap-2 text-xs" v-tooltip="timeLabel(item.progress)">
                <span class="h-2 overflow-hidden rounded-full bg-gray-100">
                    <span class="block h-full rounded-full bg-gray-400" :style="{ width: (item.progress.time_percent ?? 0) + '%' }" />
                </span>
                <span class="text-right tabular-nums text-gray-600">{{ percentLabel(item.progress.time_percent) }}</span>
            </span>
        </template>
        <template #cell(target_date)="{ item }">
            <span class="tabular-nums" :class="item.progress.days_left !== null && item.progress.days_left < 0 && item.status === 'active' ? 'text-red-600' : ''">{{ item.target_date ?? "—" }}</span>
        </template>
        <template #cell(owner)="{ item }">
            <span v-if="item.owner" class="flex items-center gap-1.5">
                <TicketUserAvatar :name="item.owner.name" :avatar="item.owner.avatar" size="sm" />
                <span v-tooltip="item.owner.name">{{ item.owner.name.split(" ")[0] }}</span>
            </span>
        </template>
        <template #cell(members)="{ item }">
            <span class="flex -space-x-1">
                <TicketUserAvatar v-for="member in item.members.slice(0, 4)" :key="member.id" v-tooltip="member.name" :name="member.name" :avatar="member.avatar" size="xs" class="ring-1 ring-white" />
                <span v-if="item.members.length > 4" class="pl-2 text-xs text-gray-500">+{{ item.members.length - 4 }}</span>
            </span>
        </template>
    </Table>

    <div v-else class="p-4">
        <p v-if="!projects.length" class="py-16 text-center text-gray-500">{{ ctrans("No projects yet. A project groups the tickets, milestones and progress notes of one big piece of work.") }}</p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="project in projects" :key="project.slug" class="relative rounded-lg border border-gray-200 p-4 transition hover:border-gray-400 hover:shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <Link :href="route('grp.projects.show', project.slug)" class="text-lg font-semibold text-gray-800 after:absolute after:inset-0">{{ project.name }}</Link>
                    <div class="flex shrink-0 flex-wrap justify-end gap-1">
                        <span v-if="project.health" class="rounded-full px-2 py-0.5 text-xs" :class="healthClasses[project.health]">{{ project.health_label }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClasses[project.status]">{{ project.status_label }}</span>
                    </div>
                </div>
                <div class="mt-3">
                    <TicketProjectProgress :progress="project.progress" :start-date="project.start_date" :target-date="project.target_date" />
                </div>
                <div v-if="project.children.length" class="relative z-10 mt-3 space-y-1 border-t border-gray-100 pt-2">
                    <p class="text-xs text-gray-400">{{ ctrans("Sub-projects") }}</p>
                    <div v-for="child in project.children" :key="child.slug" class="grid grid-cols-[minmax(0,1fr)_5rem_6.5rem] items-center gap-2 text-xs text-gray-600">
                        <Link :href="route('grp.projects.show', child.slug)" class="truncate hover:underline">{{ child.name }}</Link>
                        <span class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <span class="block h-full rounded-full" :class="isBehind(child.progress) ? 'bg-red-400' : 'bg-green-500'" :style="{ width: (child.progress.percent ?? 0) + '%' }" />
                        </span>
                        <span class="text-right tabular-nums" :class="isBehind(child.progress) && 'font-semibold text-red-600'">{{ child.progress.done }} / {{ child.progress.done + child.progress.open }} · {{ percentLabel(child.progress.percent) }}</span>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1">
                    <TicketUserAvatar v-if="project.owner" v-tooltip="ctrans('Owner') + ': ' + project.owner.name" :name="project.owner.name" :avatar="project.owner.avatar" size="sm" class="ring-2 ring-gray-400" />
                    <TicketUserAvatar v-for="member in project.members" :key="member.id" v-tooltip="member.name" :name="member.name" :avatar="member.avatar" size="sm" />
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:visible="isCreating" modal :header="ctrans('New project')" class="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="create">
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Name") }} <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" maxlength="255" required class="w-full" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Goal") }}</label>
                <Textarea v-model="form.description" rows="4" auto-resize class="w-full text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Sub-project of") }}</label>
                <Select v-model="form.parent_id" :options="parent_options" option-label="label" option-value="value" filter show-clear :placeholder="ctrans('None: a top-level project')" class="w-full" />
                <p v-if="form.errors.parent_id" class="mt-1 text-xs text-red-600">{{ form.errors.parent_id }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Start") }} <span class="text-red-500">*</span></label>
                    <DatePicker :model-value="asDate(form.start_date)" date-format="d M yy" :class="fieldFocusClass" class="w-full" @update:model-value="form.start_date = fromDate($event) ?? form.start_date" />
                    <p v-if="form.errors.start_date" class="mt-1 text-xs text-red-600">{{ form.errors.start_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target") }}</label>
                    <DatePicker :model-value="asDate(form.target_date)" date-format="d M yy" show-button-bar :class="fieldFocusClass" class="w-full" @update:model-value="form.target_date = fromDate($event) ?? ''" />
                    <p v-if="form.errors.target_date" class="mt-1 text-xs text-red-600">{{ form.errors.target_date }}</p>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Owner") }}</label>
                <Select v-model="form.owner_id" :options="staff" option-label="label" option-value="value" filter show-clear class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Team") }}</label>
                <MultiSelect v-model="form.member_ids" :options="staff" option-label="label" option-value="value" filter display="chip" class="w-full" />
            </div>
            <div class="flex w-full gap-2 pt-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isCreating = false">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width /></template>
                </Button>
                <Button type="primary" full :label="ctrans('Create')" :loading="form.processing" @click="create">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width /></template>
                </Button>
            </div>
        </form>
    </Dialog>
</template>
