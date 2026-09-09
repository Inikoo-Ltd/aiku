<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Icon from "@/Components/Icon.vue"
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Table from '@/Components/Table/Table.vue'
import BulkMoveBar from '@/Components/Production/BulkMoveBar.vue'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheckSquare, faEllipsisH } from '@fal'
import '@/Composables/Icon/ArtefactStateEnum'

library.add(faCheckSquare, faEllipsisH)

type MoveTarget = { id: number, code: string, name: string }
type MoveProps = { families_route: routeType, move_route: routeType, create_route: routeType }

const props = defineProps<{
    data: object
    tab?: string
    moveToDepartment?: MoveProps
    moveToFamily?: MoveProps
    setBatchSize?: { set_route: routeType }
}>()

const routeCurrent = route().current()
const routeParams = route().params

const tableRef = ref<any>(null)
const departmentBarRef = ref<any>(null)
const familyBarRef = ref<any>(null)
const selected = ref<Record<string, boolean>>({})
const isMoving = ref(false)
const batchSize = ref<number | null>(null)
const showMoveActions = ref(false)

const selectedIds = computed(() => Object.entries(selected.value).filter(([, on]) => on).map(([id]) => Number(id)))
const showBulkBar = computed(() => (props.moveToDepartment || props.moveToFamily || props.setBatchSize) && selectedIds.value.length > 0)

const clearSelection = () => {
    tableRef.value?.clearSelection()
    selected.value = {}
    showMoveActions.value = false
}

const submitBatchSize = () => {
    if (!props.setBatchSize || batchSize.value === null || batchSize.value < 1) return

    const count = selectedIds.value.length
    const size = batchSize.value

    router.post(
        route(props.setBatchSize.set_route.name, props.setBatchSize.set_route.parameters),
        { artefacts: selectedIds.value, recommended_batch_size: size },
        {
            preserveScroll: true,
            onStart: () => isMoving.value = true,
            onFinish: () => isMoving.value = false,
            onSuccess: () => {
                notify({
                    title: ctrans('Batch size set'),
                    text: ctrans(':count artefacts now make :size at a time', { count: count, size: size }),
                    type: 'success',
                })
                clearSelection()
                batchSize.value = null
            },
            onError: (errors) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors).join(' '), type: 'error' }),
        }
    )
}

const move = (moveRoute: routeType, payload: object, target: MoveTarget, reset: () => void) => {
    const count = selectedIds.value.length

    router.post(
        route(moveRoute.name, moveRoute.parameters),
        { artefacts: selectedIds.value, ...payload },
        {
            preserveScroll: true,
            onStart: () => isMoving.value = true,
            onFinish: () => isMoving.value = false,
            onSuccess: () => {
                notify({
                    title: ctrans('Artefacts moved'),
                    text: ctrans(':count moved to :family', { count: count, family: target.name }),
                    type: 'success',
                })
                clearSelection()
                reset()
            },
            onError: (errors) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors).join(' '), type: 'error' }),
        }
    )
}

const submitMoveToDepartment = (target: MoveTarget) => {
    if (props.moveToDepartment) {
        move(props.moveToDepartment.move_route, { artefact_department_id: target.id }, target, () => departmentBarRef.value?.reset())
    }
}

const submitMoveToFamily = (target: MoveTarget) => {
    if (props.moveToFamily) {
        move(props.moveToFamily.move_route, { artefact_family_id: target.id }, target, () => familyBarRef.value?.reset())
    }
}

function productionRoute(artefact: { slug: string }) {
    switch (routeCurrent) {
        case 'grp.org.productions.show.crafts.artefacts.index':
        case 'grp.org.productions.show.crafts.artefact_departments.show':
        case 'grp.org.productions.show.crafts.artefact_families.show':
            return route(
                'grp.org.productions.show.crafts.artefacts.show',
                [routeParams['organisation'], routeParams['production'], artefact.slug]);
    }
}
</script>

<template>
    <div
        v-if="showBulkBar"
        class="sticky top-0 z-10 xmx-4 mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 xrounded-md bg-green-100 border-y border-slate-300 px-4 py-2.5 xshadow-lg mb-2"
        role="region"
        :aria-label="ctrans('Bulk actions')">
        <span class="flex items-center gap-2 whitespace-nowrap font-medium" aria-live="polite">
            <FontAwesomeIcon icon="fal fa-check-square" fixed-width aria-hidden="true" />
            {{ selectedIds.length === 1 ? ctrans('1 artefact selected') : ctrans(':count artefacts selected', { count: selectedIds.length }) }}
        </span>

        <button type="button" class="text-xs xtext-indigo-100 underline underline-offset-2 hover:text-red-500" @click="clearSelection">
            {{ ctrans('Clear') }}
        </button>

        <div class="ml-auto flex flex-wrap items-center gap-x-6 gap-y-2">
            <div v-if="setBatchSize" class="flex items-center gap-2">
                <label class="whitespace-nowrap text-sm" for="bulkBatchSize">{{ ctrans('Batch size') }}</label>
                <input
                    id="bulkBatchSize"
                    v-model.number="batchSize"
                    type="number"
                    min="1"
                    class="w-24 rounded border-gray-300 py-1 text-sm"
                    :placeholder="ctrans('Units')"
                    @keyup.enter="submitBatchSize" />
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white disabled:opacity-50"
                    :disabled="isMoving || batchSize === null || batchSize < 1"
                    @click="submitBatchSize">
                    {{ ctrans('Set') }}
                </button>
            </div>

            <button
                v-if="(moveToFamily || moveToDepartment) && !showMoveActions"
                type="button"
                class="flex items-center gap-1.5 whitespace-nowrap text-sm underline underline-offset-2 hover:text-indigo-600"
                @click="showMoveActions = true">
                <FontAwesomeIcon icon="fal fa-ellipsis-h" fixed-width aria-hidden="true" />
                {{ ctrans('Move') }}
            </button>

            <BulkMoveBar
                v-if="moveToFamily && showMoveActions"
                ref="familyBarRef"
                :fetchRoute="moveToFamily.families_route"
                :placeholder="ctrans('Move to family')"
                :noOptionsText="ctrans('No families yet')"
                :moveLabel="ctrans('Move')"
                :pickFirstLabel="ctrans('Pick a family first')"
                :createRoute="moveToFamily.create_route"
                :createLabel="ctrans('New family')"
                :loading="isMoving"
                @move="submitMoveToFamily" />

            <BulkMoveBar
                v-if="moveToDepartment && showMoveActions"
                ref="departmentBarRef"
                :fetchRoute="moveToDepartment.families_route"
                :placeholder="ctrans('Move to department')"
                :noOptionsText="ctrans('No departments yet')"
                :moveLabel="ctrans('Move')"
                :pickFirstLabel="ctrans('Pick a department first')"
                :createRoute="moveToDepartment.create_route"
                :createLabel="ctrans('New department')"
                :loading="isMoving"
                @move="submitMoveToDepartment" />
        </div>
    </div>

    <Table ref="tableRef" :resource="data" :name="tab" class="mt-5" :isCheckBox="!!(moveToDepartment || moveToFamily || setBatchSize)" checkboxKey="id" @onSelectRow="(rows) => selected = { ...rows }">
        <template #cell(state)="{ item: artefact }">
            <Icon :data="artefact.state" />
        </template>
        <template #cell(code)="{ item: production }">
            <Link :href="productionRoute(production)" class="primaryLink">
                {{ production['code'] }}
            </Link>
        </template>
        <template #cell(artefact_department_name)="{ item }">
            <Link v-if="item.artefact_department_slug" :href="route('grp.org.productions.show.crafts.artefact_departments.show', [routeParams['organisation'], routeParams['production'], item.artefact_department_slug])" class="secondaryLink">
                {{ item.artefact_department_name }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(artefact_family_name)="{ item }">
            <Link v-if="item.artefact_family_slug" :href="route('grp.org.productions.show.crafts.artefact_families.show', [routeParams['organisation'], routeParams['production'], item.artefact_family_slug])" class="secondaryLink">
                {{ item.artefact_family_name }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(tags)="{ item }">
            <div class="flex flex-wrap gap-1">
                <span v-for="tag in item.tags" :key="tag" class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">#{{ tag }}</span>
            </div>
        </template>
    </Table>
</template>
