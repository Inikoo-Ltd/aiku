<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Table from "@/Components/Table/Table.vue"
import BulkMoveBar from "@/Components/Production/BulkMoveBar.vue"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckSquare } from "@fal"
import "@/Composables/Icon/ArtefactStateEnum"

library.add(faCheckSquare)

type Department = { id: number; code: string; name: string }

const props = defineProps<{
    data: object
    tab?: string
    moveToDepartment?: {
        departments_route: routeType
        move_route: routeType
        create_route: routeType
    }
}>()

const routeParams = route().params

const tableRef = ref<any>(null)
const barRef = ref<any>(null)
const selected = ref<Record<string, boolean>>({})
const isMoving = ref(false)

const selectedIds = computed(() => Object.entries(selected.value).filter(([, on]) => on).map(([id]) => Number(id)))

const clearSelection = () => {
    tableRef.value?.clearSelection()
    selected.value = {}
}

const submitMove = (department: Department) => {
    if (!props.moveToDepartment) return

    const count = selectedIds.value.length

    router.post(
        route(props.moveToDepartment.move_route.name, props.moveToDepartment.move_route.parameters),
        { families: selectedIds.value, artefact_department_id: department.id },
        {
            preserveScroll: true,
            onStart: () => isMoving.value = true,
            onFinish: () => isMoving.value = false,
            onSuccess: () => {
                notify({
                    title: ctrans('Families moved'),
                    text: ctrans(':count moved to :family', { count: count, family: department.name }),
                    type: 'success',
                })
                clearSelection()
                barRef.value?.reset()
            },
            onError: (errors) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors).join(' '), type: 'error' }),
        }
    )
}

const familyRoute = (family: { slug: string }) =>
    route("grp.org.productions.show.crafts.artefact_families.show", [routeParams["organisation"], routeParams["production"], family.slug])
</script>

<template>
    
    <div
        v-if="moveToDepartment && selectedIds.length"
        class="sticky top-0 z-10 mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md bg-green-100 px-4 py-2.5 mb-2"
        role="region"
        :aria-label="ctrans('Bulk actions')">
        <span class="flex items-center gap-2 whitespace-nowrap font-medium" aria-live="polite">
            <FontAwesomeIcon icon="fal fa-check-square" fixed-width aria-hidden="true" />
            {{ selectedIds.length === 1 ? ctrans('1 family selected') : ctrans(':count families selected', { count: selectedIds.length }) }}
        </span>

        <button type="button" class="text-xs xtext-indigo-100 underline underline-offset-2 hover:text-red-500" @click="clearSelection">
            {{ ctrans('Clear') }}
        </button>

        <div class="ml-auto">
            <BulkMoveBar
                ref="barRef"
                :fetchRoute="moveToDepartment.departments_route"
                :placeholder="ctrans('Move to department')"
                :noOptionsText="ctrans('No departments yet')"
                :moveLabel="ctrans('Move')"
                :pickFirstLabel="ctrans('Pick a department first')"
                :createRoute="moveToDepartment.create_route"
                :createLabel="ctrans('New department')"
                :loading="isMoving"
                @move="submitMove" />
        </div>
    </div>

    <Table ref="tableRef" :resource="data" :name="tab" class="mt-5" :isCheckBox="!!moveToDepartment" checkboxKey="id" @onSelectRow="(rows) => selected = { ...rows }">
        <template #cell(code)="{ item: family }">
            <Link :href="familyRoute(family)" class="primaryLink">{{ family.code }}</Link>
        </template>
        <template #cell(number_artefacts_without_recipe)="{ item }">
            <span :class="item.number_artefacts_without_recipe ? 'text-red-500' : ''">{{ item.number_artefacts_without_recipe }}</span>
        </template>
        <template #cell(number_artefacts_without_batch_size)="{ item }">
            <span :class="item.number_artefacts_without_batch_size ? 'text-red-500' : ''">{{ item.number_artefacts_without_batch_size }}</span>
        </template>
        <template #cell(artefact_department_name)="{ item }">
            <Link v-if="item.artefact_department_slug" :href="route('grp.org.productions.show.crafts.artefact_departments.show', [routeParams['organisation'], routeParams['production'], item.artefact_department_slug])" class="secondaryLink">
                {{ item.artefact_department_name }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
