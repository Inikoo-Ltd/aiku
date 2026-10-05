<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Icon from "@/Components/Icon.vue"
import { Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Table from '@/Components/Table/Table.vue'
import BulkMoveBar from '@/Components/Production/BulkMoveBar.vue'
import ModalUnifiedManufactureTask, { SetRecipeProps, UnifiedRecipeArtefact } from '@/Components/Production/ModalUnifiedManufactureTask.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheckSquare, faExclamationTriangle, faChevronDown, faTasks } from '@fal'
import Modal from '@/Components/Utils/Modal.vue'
import Popover from '@/Components/Popover.vue'
import '@/Composables/Icon/ArtefactStateEnum'

library.add(faCheckSquare, faExclamationTriangle, faChevronDown, faTasks)

type MoveTarget = { id: number, code: string, name: string }
type MoveProps = { families_route: routeType, move_route: routeType, create_route: routeType }

const props = defineProps<{
    data: object
    tab?: string
    moveToDepartment?: MoveProps
    moveToFamily?: MoveProps
    setBatchSize?: { set_route: routeType }
    setShelfLife?: { set_route: routeType }
    setState?: { set_state_route: routeType }
    setRecipe?: SetRecipeProps
}>()

const routeCurrent = route().current()
const routeParams = route().params

const tableRef = ref<any>(null)
const departmentBarRef = ref<any>(null)
const familyBarRef = ref<any>(null)
const selected = ref<Record<string, boolean>>({})
const isMoving = ref(false)
const batchSize = ref<number | null>(null)
const shelfLifeDays = ref<number | null>(null)
const confirmingDiscontinue = ref(false)
const isRecipeModalOpen = ref(false)
const bulkAction = ref('batch_size')

const bulkActions = computed(() => [
    props.setBatchSize ? { value: 'batch_size', label: ctrans('Batch size') } : null,
    props.setShelfLife ? { value: 'shelf_life', label: ctrans('Shelf life') } : null,
    props.setRecipe ? { value: 'manufacture_task', label: ctrans('Manufacture task') } : null,
    props.moveToFamily ? { value: 'move_family', label: ctrans('Move to family') } : null,
    props.moveToDepartment ? { value: 'move_department', label: ctrans('Move to department') } : null,
    props.setState ? { value: 'discontinue', label: ctrans('Discontinue') } : null,
    props.setState ? { value: 'activate', label: ctrans('Make active') } : null,
].filter(Boolean) as { value: string, label: string }[])

const currentAction = computed(() => bulkActions.value.find(action => action.value === bulkAction.value))

const selectedIds = computed(() => Object.entries(selected.value).filter(([, on]) => on).map(([id]) => Number(id)))
const showBulkBar = computed(() => (props.moveToDepartment || props.moveToFamily || props.setBatchSize || props.setShelfLife || props.setState || props.setRecipe) && selectedIds.value.length > 0)

const knownArtefacts = ref<Record<number, UnifiedRecipeArtefact>>({})

watch([selectedIds, () => props.data], () => {
    const rows = ((props.data as { data?: UnifiedRecipeArtefact[] })?.data ?? [])
    rows.filter(row => selectedIds.value.includes(row.id))
        .forEach(row => knownArtefacts.value[row.id] = { id: row.id, code: row.code, name: row.name })
}, { immediate: true })

const selectedArtefacts = computed<UnifiedRecipeArtefact[]>(() =>
    selectedIds.value.map(id => knownArtefacts.value[id] ?? { id: id, code: null, name: null })
)

const onRecipeSaved = () => {
    isRecipeModalOpen.value = false
    clearSelection()
}

const clearSelection = () => {
    confirmingDiscontinue.value = false
    tableRef.value?.clearSelection()
    selected.value = {}
}

const applyState = (state: string, title: string) => {
    if (!props.setState) return

    const count = selectedIds.value.length
    const artefacts = [...selectedIds.value]

    /* The answer is already given, so the dialog goes before the table reloads under it. */
    confirmingDiscontinue.value = false

    router.post(
        route(props.setState.set_state_route.name, props.setState.set_state_route.parameters),
        { artefacts: artefacts, state: state },
        {
            preserveScroll: true,
            onStart: () => isMoving.value = true,
            onFinish: () => isMoving.value = false,
            onSuccess: () => {
                notify({
                    title: title,
                    text: count === 1 ? ctrans('1 artefact changed') : ctrans(':count artefacts changed', { count: count }),
                    type: 'success',
                })
                clearSelection()
            },
            onError: (errors) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors).join(' '), type: 'error' }),
        }
    )
}

const submitDiscontinue = () => applyState('discontinued', ctrans('Artefacts discontinued'))
const submitActivate = () => applyState('active', ctrans('Artefacts made active'))

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

const submitShelfLife = () => {
    if (!props.setShelfLife || shelfLifeDays.value === null || shelfLifeDays.value < 1) return

    const count = selectedIds.value.length
    const days = shelfLifeDays.value

    router.post(
        route(props.setShelfLife.set_route.name, props.setShelfLife.set_route.parameters),
        { artefacts: selectedIds.value, shelf_life_days: days },
        {
            preserveScroll: true,
            onStart: () => isMoving.value = true,
            onFinish: () => isMoving.value = false,
            onSuccess: () => {
                notify({
                    title: ctrans('Shelf life set'),
                    text: ctrans(':count artefacts now keep for :days days', { count: count, days: days }),
                    type: 'success',
                })
                clearSelection()
                shelfLifeDays.value = null
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
        class="sticky top-0 z-10 mx-4 mt-4 mb-2 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md border border-slate-300 bg-slate-50 px-4 py-2.5"
        role="region"
        :aria-label="ctrans('Bulk actions')">
        <span class="flex items-center gap-2 whitespace-nowrap font-medium" aria-live="polite">
            <FontAwesomeIcon icon="fal fa-check-square" fixed-width aria-hidden="true" />
            {{ selectedIds.length === 1 ? ctrans('1 artefact selected') : ctrans(':count artefacts selected', { count: selectedIds.length }) }}
        </span>

        <button type="button" class="text-xs underline underline-offset-2 hover:text-red-500" @click="clearSelection">
            {{ ctrans('Clear') }}
        </button>

        <div class="ml-auto flex flex-wrap items-center gap-x-3 gap-y-2">

            <div v-if="setBatchSize && bulkAction === 'batch_size'" class="flex items-center gap-2">
                <input
                    id="bulkBatchSize"
                    v-model.number="batchSize"
                    type="number"
                    min="1"
                    class="w-24 rounded border-gray-300 py-1 text-sm"
                    :placeholder="ctrans('Units')"
                    @keyup.enter="submitBatchSize" />
                <Button
                    type="primary"
                    size="s"
                    :label="ctrans('Set')"
                    :loading="isMoving"
                    :disabled="batchSize === null || batchSize < 1"
                    @click="submitBatchSize" />
            </div>

            <div v-if="setShelfLife && bulkAction === 'shelf_life'" class="flex items-center gap-2">
                <input
                    id="bulkShelfLife"
                    v-model.number="shelfLifeDays"
                    type="number"
                    min="1"
                    class="w-24 rounded border-gray-300 py-1 text-sm"
                    :placeholder="ctrans('Days')"
                    @keyup.enter="submitShelfLife" />
                <Button
                    type="primary"
                    size="s"
                    :label="ctrans('Set')"
                    :loading="isMoving"
                    :disabled="shelfLifeDays === null || shelfLifeDays < 1"
                    @click="submitShelfLife" />
            </div>

            <Button
                v-if="setRecipe && bulkAction === 'manufacture_task'"
                type="primary"
                size="s"
                icon="fal fa-tasks"
                :label="ctrans('Make a unified manufacture task')"
                @click="isRecipeModalOpen = true" />

            <Button
                v-if="setState && bulkAction === 'activate'"
                type="primary"
                size="s"
                :label="ctrans('Make active')"
                :loading="isMoving"
                @click="submitActivate" />

            <Button
                v-if="setState && bulkAction === 'discontinue'"
                type="red"
                size="s"
                :label="ctrans('Discontinue')"
                :loading="isMoving"
                @click="confirmingDiscontinue = true" />

            <BulkMoveBar
                v-if="moveToFamily && bulkAction === 'move_family'"
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
                v-if="moveToDepartment && bulkAction === 'move_department'"
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

            <div class="relative [&_button]:outline-none [&_button]:ring-0 [&_button:focus]:outline-none [&_button:focus]:ring-0">
                <Popover width="w-48" position="right-0">
                    <template #button>
                        <div class="flex w-48 items-center justify-between gap-2 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:outline-none">
                            <span class="truncate">{{ currentAction?.label }}</span>
                            <FontAwesomeIcon icon="fal fa-chevron-down" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                        </div>
                    </template>

                    <template #content="{ close }">
                        <div class="flex flex-col">
                            <button
                                v-for="action in bulkActions"
                                :key="action.value"
                                type="button"
                                class="rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100"
                                :class="action.value === bulkAction ? 'bulk-action-current font-medium' : 'text-gray-700'"
                                @click="bulkAction = action.value; close()">
                                {{ action.label }}
                            </button>
                        </div>
                    </template>
                </Popover>
            </div>
        </div>
    </div>

    <Modal :isOpen="confirmingDiscontinue" width="w-full max-w-lg" @onClose="confirmingDiscontinue = false">
        <div class="p-2">
            <div class="flex items-start gap-3">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-1 text-red-500" fixed-width aria-hidden="true" />
                <div class="text-left">
                    <h3 class="font-medium">
                        {{ selectedIds.length === 1 ? ctrans('Discontinue 1 artefact?') : ctrans('Discontinue :count artefacts?', { count: selectedIds.length }) }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ ctrans('A discontinued artefact leaves the working lists and keeps its recipe. Pick Make active to bring it back.') }}
                    </p>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <Button type="tertiary" size="s" :label="ctrans('Cancel')" @click="confirmingDiscontinue = false" />
                <Button type="red" size="s" :label="ctrans('Yes, discontinue')" :loading="isMoving" @click="submitDiscontinue" />
            </div>
        </div>
    </Modal>

    <ModalUnifiedManufactureTask
        v-if="setRecipe"
        :isOpen="isRecipeModalOpen"
        :artefacts="selectedArtefacts"
        :setRecipe="setRecipe"
        @close="isRecipeModalOpen = false"
        @saved="onRecipeSaved" />

    <Table ref="tableRef" :resource="data" :name="tab" class="mt-5" :isCheckBox="!!(moveToDepartment || moveToFamily || setBatchSize || setShelfLife || setState || setRecipe)" checkboxKey="id" @onSelectRow="(rows) => selected = { ...rows }">
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
        <template #cell(batch_in_skos)="{ item }">
            <span v-if="item.batch_in_skos">
                {{ item.batch_in_skos }}
                <span class="text-gray-400 text-xs">&times;{{ item.packed_in }}</span>
                <span v-if="item.suggested_batch_size" class="text-gray-500 text-xs" :title="ctrans('Nearest batch that is whole SKOs')">
                    &rarr; {{ item.suggested_batch_size }}
                </span>
            </span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(tags)="{ item }">
            <div class="flex flex-wrap gap-1">
                <span v-for="tag in item.tags" :key="tag" class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">#{{ tag }}</span>
            </div>
        </template>
    </Table>
</template>

<style scoped>
.bulk-action-current {
    color: var(--theme-color-4);
}
</style>
