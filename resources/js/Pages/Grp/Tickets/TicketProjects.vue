<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, useForm } from "@inertiajs/vue3"
import { Dialog, Select, MultiSelect } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketProjectProgress from "@/Components/Tickets/TicketProjectProgress.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faProjectDiagram, faPlus } from "@fal"

library.add(faProjectDiagram, faPlus)

type Person = { id: number; name: string; avatar: Record<string, string> | null }

const props = defineProps<{
    pageHead: any
    title: string
    projects: {
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
        progress: any
    }[]
    can_create: boolean
    staff: { label: string; value: number }[]
    store_route: { name: string }
}>()

const isCreating = ref(false)
const form = useForm({
    name: "",
    description: "",
    start_date: new Date().toISOString().slice(0, 10),
    target_date: "",
    owner_id: null as number | null,
    member_ids: [] as number[],
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
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Button v-if="can_create" icon="fal fa-plus" :label="ctrans('New project')" @click="isCreating = true" />
        </template>
    </PageHeading>

    <div class="p-4">
        <p v-if="!projects.length" class="py-16 text-center text-gray-500">{{ ctrans("No projects yet. A project groups the tickets, milestones and progress notes of one big piece of work.") }}</p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="project in projects"
                :key="project.slug"
                :href="route('grp.tickets.projects.show', project.slug)"
                class="block rounded-lg border border-gray-200 p-4 transition hover:border-gray-400 hover:shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-800">{{ project.name }}</h3>
                    <div class="flex shrink-0 flex-wrap justify-end gap-1">
                        <span v-if="project.health" class="rounded-full px-2 py-0.5 text-xs" :class="healthClasses[project.health]">{{ project.health_label }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClasses[project.status]">{{ project.status_label }}</span>
                    </div>
                </div>
                <div class="mt-3">
                    <TicketProjectProgress :progress="project.progress" :start-date="project.start_date" :target-date="project.target_date" />
                </div>
                <div class="mt-3 flex items-center gap-1">
                    <TicketUserAvatar v-if="project.owner" v-tooltip="ctrans('Owner') + ': ' + project.owner.name" :name="project.owner.name" :avatar="project.owner.avatar" size="sm" class="ring-2 ring-[--app-accent-strong]" />
                    <TicketUserAvatar v-for="member in project.members" :key="member.id" v-tooltip="member.name" :name="member.name" :avatar="member.avatar" size="sm" />
                </div>
            </Link>
        </div>
    </div>

    <Dialog v-model:visible="isCreating" modal :header="ctrans('New project')" class="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="create">
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Name") }}</label>
                <input v-model="form.name" maxlength="255" required class="w-full rounded-md border-gray-300 text-sm" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Goal") }}</label>
                <textarea v-model="form.description" rows="4" class="w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Start") }}</label>
                    <input v-model="form.start_date" type="date" required class="w-full rounded-md border-gray-300 text-sm" />
                    <p v-if="form.errors.start_date" class="mt-1 text-xs text-red-600">{{ form.errors.start_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target") }}</label>
                    <input v-model="form.target_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
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
            <div class="flex justify-end gap-2 pt-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isCreating = false" />
                <Button :label="ctrans('Create')" :loading="form.processing" @click="create" />
            </div>
        </form>
    </Dialog>
</template>
