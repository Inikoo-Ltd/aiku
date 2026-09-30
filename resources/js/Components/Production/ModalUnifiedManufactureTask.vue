<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import { notify } from '@kyvg/vue3-notification'
import InputNumber from 'primevue/inputnumber'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faExclamationTriangle, faPlus, faTrashAlt, faArrowUp, faArrowDown, faLayerGroup, faUndo } from '@fal'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureSelectInfiniteScroll from '@/Components/Pure/PureSelectInfiniteScroll.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { routeType } from '@/types/route'
import { ctrans } from '@/Composables/useTrans'

library.add(faExclamationTriangle, faPlus, faTrashAlt, faArrowUp, faArrowDown, faLayerGroup, faUndo)

export interface UnifiedRecipeArtefact {
    id: number
    code: string | null
    name: string | null
}

export interface SetRecipeProps {
    set_route: routeType
    task_options: routeType
    raw_material_options: routeType
    create_task_route?: routeType
}

interface RawMaterialOption {
    id: number
    code: string
    description: string
    unit: string | null
}

interface DraftRawMaterial {
    raw_material_id: number
    code: string
    description: string
    unit: string | null
    quantity_per_unit: number
}

interface DraftStep {
    key: number
    manufacture_task_id: number | null
    units_per_artefact: number
    raw_materials: DraftRawMaterial[]
    artefact_raw_materials: Record<number, DraftRawMaterial[]>
    newRawMaterial: RawMaterialOption | null
    newRawMaterialQuantity: number
}

const props = defineProps<{
    isOpen: boolean
    artefacts: UnifiedRecipeArtefact[]
    setRecipe: SetRecipeProps
}>()

const emits = defineEmits<{
    (e: 'close'): void
    (e: 'saved'): void
}>()

let nextKey = 1
const newStep = (): DraftStep => ({
    key: nextKey++,
    manufacture_task_id: null,
    units_per_artefact: 1,
    raw_materials: [],
    artefact_raw_materials: {},
    newRawMaterial: null,
    newRawMaterialQuantity: 1,
})

const steps = ref<DraftStep[]>([newStep()])
const stepsContainer = ref<HTMLElement | null>(null)
const focusedArtefactId = ref<number | null>(null)
const acknowledged = ref(false)
const processing = ref(false)
const errors = ref<Record<string, string>>({})

watch(() => props.isOpen, isOpen => {
    if (isOpen) {
        acknowledged.value = false
        errors.value = {}
    }
})

watch(() => props.artefacts, artefacts => {
    if (focusedArtefactId.value !== null && !artefacts.some(artefact => artefact.id === focusedArtefactId.value)) {
        focusedArtefactId.value = null
    }
})

const artefactLabel = (artefact: UnifiedRecipeArtefact | undefined) => artefact?.code ?? (artefact ? `#${artefact.id}` : '')

const focusedArtefact = computed(() => props.artefacts.find(artefact => artefact.id === focusedArtefactId.value))

const hasOwnMaterials = (step: DraftStep, artefactId: number) => artefactId in step.artefact_raw_materials

const artefactHasOwnMaterials = (artefactId: number) => steps.value.some(step => hasOwnMaterials(step, artefactId))

const artefactsWithOwnMaterials = (step: DraftStep) => props.artefacts.filter(artefact => hasOwnMaterials(step, artefact.id))

const hasDifferentMaterials = (step: DraftStep) => focusedArtefactId.value === null
    ? artefactsWithOwnMaterials(step).length > 0
    : hasOwnMaterials(step, focusedArtefactId.value)

const differentMaterialsNote = (step: DraftStep) => focusedArtefact.value
    ? ctrans(':artefact has its own raw materials on this step', { artefact: artefactLabel(focusedArtefact.value) })
    : ctrans('Own raw materials on this step: :artefacts', { artefacts: artefactsWithOwnMaterials(step).map(artefact => artefactLabel(artefact)).join(', ') })

const editableMaterials = (step: DraftStep): DraftRawMaterial[] | null => {
    if (focusedArtefactId.value === null) return step.raw_materials

    return step.artefact_raw_materials[focusedArtefactId.value] ?? null
}

const useOwnMaterials = (step: DraftStep) => {
    if (focusedArtefactId.value === null) return

    step.artefact_raw_materials[focusedArtefactId.value] = step.raw_materials.map(material => ({ ...material }))
}

const useSharedMaterials = (step: DraftStep) => {
    if (focusedArtefactId.value === null) return

    delete step.artefact_raw_materials[focusedArtefactId.value]
}

const duplicatedTaskIds = computed(() => {
    const taskIds = steps.value.map(step => step.manufacture_task_id).filter((id): id is number => id !== null)

    return new Set(taskIds.filter((id, index) => taskIds.indexOf(id) !== index))
})

const hasMissingTask = computed(() => steps.value.some(step => step.manufacture_task_id === null))

const canSave = computed(() =>
    props.artefacts.length > 0
    && steps.value.length > 0
    && !hasMissingTask.value
    && duplicatedTaskIds.value.size === 0
    && steps.value.every(step => step.units_per_artefact > 0)
    && acknowledged.value
)

const blockingReason = computed(() => {
    if (!steps.value.length) return ctrans('Add at least one step')
    if (hasMissingTask.value) return ctrans('Pick a task for every step')
    if (duplicatedTaskIds.value.size) return ctrans('A task can only be used once in a recipe')
    if (!acknowledged.value) return ctrans('Tick the box to confirm the existing steps will be replaced')

    return null
})

const addStep = async () => {
    steps.value.push(newStep())
    await nextTick()
    stepsContainer.value?.scrollTo({ top: stepsContainer.value.scrollHeight, behavior: 'smooth' })
}

const removeStep = (index: number) => steps.value.splice(index, 1)

const moveStep = (index: number, offset: number) => {
    const target = index + offset
    if (target < 0 || target >= steps.value.length) return

    const [step] = steps.value.splice(index, 1)
    steps.value.splice(target, 0, step)
}

const addRawMaterial = (step: DraftStep) => {
    const option = step.newRawMaterial
    const materials = editableMaterials(step)
    if (!option || !materials || !(step.newRawMaterialQuantity > 0)) return

    const existing = materials.find(material => material.raw_material_id === option.id)
    if (existing) {
        existing.quantity_per_unit = step.newRawMaterialQuantity
    } else {
        materials.push({
            raw_material_id: option.id,
            code: option.code,
            description: option.description,
            unit: option.unit,
            quantity_per_unit: step.newRawMaterialQuantity,
        })
    }

    step.newRawMaterial = null
    step.newRawMaterialQuantity = 1
}

const removeRawMaterial = (materials: DraftRawMaterial[], index: number) => materials.splice(index, 1)

const stepError = (index: number) =>
    errors.value[`steps.${index}.manufacture_task_id`]
    ?? errors.value[`steps.${index}.units_per_artefact`]
    ?? Object.entries(errors.value).find(([key]) => key.startsWith(`steps.${index}.raw_materials`) || key.startsWith(`steps.${index}.artefact_raw_materials`))?.[1]

const generalError = computed(() => errors.value.steps ?? errors.value.artefacts)

const toPayloadMaterials = (materials: DraftRawMaterial[]) => materials.map(material => ({
    raw_material_id: material.raw_material_id,
    quantity_per_unit: material.quantity_per_unit,
}))

const reset = () => {
    steps.value = [newStep()]
    focusedArtefactId.value = null
    acknowledged.value = false
    errors.value = {}
}

const save = () => {
    if (!canSave.value) return

    const count = props.artefacts.length

    router.post(
        route(props.setRecipe.set_route.name, props.setRecipe.set_route.parameters),
        {
            artefacts: props.artefacts.map(artefact => artefact.id),
            steps: steps.value.map((step, index) => ({
                manufacture_task_id: step.manufacture_task_id,
                position: index + 1,
                units_per_artefact: step.units_per_artefact,
                raw_materials: toPayloadMaterials(step.raw_materials),
                artefact_raw_materials: artefactsWithOwnMaterials(step).map(artefact => ({
                    artefact_id: artefact.id,
                    raw_materials: toPayloadMaterials(step.artefact_raw_materials[artefact.id]),
                })),
            })),
        },
        {
            preserveScroll: true,
            onStart: () => processing.value = true,
            onFinish: () => processing.value = false,
            onSuccess: () => {
                notify({
                    title: ctrans('Manufacture tasks set'),
                    text: count === 1 ? ctrans('1 artefact now follows this recipe') : ctrans(':count artefacts now follow this recipe', { count: count }),
                    type: 'success',
                })
                reset()
                emits('saved')
            },
            onError: (responseErrors) => {
                errors.value = responseErrors
                notify({ title: ctrans('Something went wrong'), text: Object.values(responseErrors)[0], type: 'error' })
            },
        }
    )
}
</script>

<template>
    <Modal :isOpen="isOpen" width="w-full max-w-6xl" @onClose="emits('close')">
        <div class="flex flex-col gap-5 md:max-h-[calc(100vh-6rem)] md:flex-row">
            <div class="flex min-w-0 flex-1 flex-col gap-4 md:min-h-0">
                <div class="shrink-0">
                    <h2 class="text-lg font-semibold">{{ ctrans('Make a unified manufacture task') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ ctrans('Set up the steps once and give them to every selected artefact, in the order they are done.') }}
                    </p>
                </div>

                <div v-if="generalError" class="shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ generalError }}</div>

                <div ref="stepsContainer" class="space-y-4 md:min-h-0 md:flex-1 md:overflow-y-auto md:pb-2 md:pr-1 [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300">
                    <article
                        v-for="(step, index) in steps"
                        :key="step.key"
                        class="relative rounded-lg border bg-white"
                        :class="hasDifferentMaterials(step) ? 'border-red-300' : 'border-gray-200'">
                        <span
                            v-if="hasDifferentMaterials(step)"
                            v-tooltip="differentMaterialsNote(step)"
                            class="absolute right-2.5 top-1 cursor-help text-lg font-bold leading-none text-red-500"
                            :aria-label="differentMaterialsNote(step)">*</span>
                        <header class="flex flex-wrap items-end gap-x-4 gap-y-3 border-b border-gray-100 px-4 py-3">
                            <span class="theme-soft mb-1 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                                {{ index + 1 }}
                            </span>
    
                            <label class="block">
                                <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                                    {{ ctrans('Task') }}
                                    <InformationIcon :information="ctrans('The piece of work an artisan does, such as pouring or wrapping. Tasks are set up once for the factory and reused by every artefact that needs them.')" />
                                </span>
                                <div class="w-72">
                                    <PureSelectInfiniteScroll
                                        v-model="step.manufacture_task_id"
                                        :fetchRoute="setRecipe.task_options"
                                        :placeholder="ctrans('Select task')"
                                        :noOptionsText="ctrans('No manufacture tasks yet')"
                                        labelProp="name"
                                        labelAdditionalProp="code" />
                                </div>
                            </label>
    
                            <label class="block">
                                <span class="mb-1 flex items-center gap-1 text-xs text-gray-500">
                                    {{ ctrans('Units per artefact') }}
                                    <InformationIcon :information="ctrans('How many units of this task one artefact needs. A job order for 10 artefacts at 2 units per artefact asks the artisan for 20 units of work.')" />
                                </span>
                                <InputNumber
                                    v-model="step.units_per_artefact"
                                    :min="0.001"
                                    :minFractionDigits="0"
                                    :maxFractionDigits="3"
                                    :useGrouping="false"
                                    size="small"
                                    inputClass="w-24" />
                            </label>
    
                            <div class="ml-auto flex items-center gap-1">
                                <Button type="transparent" size="xs" icon="fal fa-arrow-up" :disabled="index === 0" :tooltip="ctrans('Move step up')" @click="moveStep(index, -1)" />
                                <Button type="transparent" size="xs" icon="fal fa-arrow-down" :disabled="index === steps.length - 1" :tooltip="ctrans('Move step down')" @click="moveStep(index, 1)" />
                                <Button type="transparent" size="xs" icon="fal fa-trash-alt" :disabled="steps.length === 1" :tooltip="ctrans('Remove step')" @click="removeStep(index)" />
                            </div>
    
                            <p v-if="step.manufacture_task_id !== null && duplicatedTaskIds.has(step.manufacture_task_id)" class="w-full text-xs text-red-600">
                                {{ ctrans('This task is already used in another step') }}
                            </p>
                            <p v-else-if="stepError(index)" class="w-full text-xs text-red-600">{{ stepError(index) }}</p>
                        </header>
    
                        <section class="px-4 py-3" :class="focusedArtefact && hasOwnMaterials(step, focusedArtefact.id) ? 'bg-red-50/60' : 'bg-gray-50'">
                            <div class="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                                    {{ ctrans('Raw materials') }}
                                    <InformationIcon :information="ctrans('What this step consumes to produce one unit of work. Every selected artefact gets these materials, unless you pick an artefact on the right and give it different ones.')" />
                                </span>
                                <span class="theme-soft rounded px-1.5 py-0.5 text-xs">
                                    {{ focusedArtefact ? ctrans('For :artefact', { artefact: artefactLabel(focusedArtefact) }) : ctrans('For all artefacts') }}
                                </span>
    
                                <Button
                                    v-if="focusedArtefact && hasOwnMaterials(step, focusedArtefact.id)"
                                    class="ml-auto"
                                    type="tertiary"
                                    size="xxs"
                                    icon="fal fa-undo"
                                    :label="ctrans('Use the same materials as all artefacts')"
                                    @click="useSharedMaterials(step)" />
                            </div>
    
                            <template v-if="editableMaterials(step) === null">
                                <p class="text-xs text-gray-500">
                                    {{ ctrans(':artefact uses the same materials as all artefacts on this step.', { artefact: artefactLabel(focusedArtefact) }) }}
                                </p>
                                <p v-if="!step.raw_materials.length" class="mt-1 text-xs text-gray-400">
                                    {{ ctrans('No shared raw materials on this step yet.') }}
                                </p>
                                <ul v-else class="mt-2 divide-y divide-gray-200 border-y border-gray-200 opacity-60">
                                    <li v-for="material in step.raw_materials" :key="material.raw_material_id" class="flex items-center gap-x-3 py-1.5 text-xs text-gray-600">
                                        <div class="min-w-0 flex-1 truncate" :title="material.description">
                                            <span class="font-bold">{{ material.code }}</span>
                                            <span class="ml-2 opacity-70">{{ material.description }}</span>
                                        </div>
                                        <span class="w-24 shrink-0 tabular-nums">{{ material.quantity_per_unit }}</span>
                                        <span class="w-12 shrink-0 text-gray-500">{{ material.unit }}</span>
                                    </li>
                                </ul>
                                <Button
                                    class="mt-3"
                                    type="secondary"
                                    size="xs"
                                    icon="fal fa-layer-group"
                                    :label="ctrans('Use different materials for :artefact', { artefact: artefactLabel(focusedArtefact) })"
                                    @click="useOwnMaterials(step)" />
                            </template>
    
                            <template v-else>
                                <p v-if="!editableMaterials(step)!.length" class="text-xs text-gray-400">
                                    {{ ctrans('No raw materials for this step yet.') }}
                                </p>
    
                                <ul v-else class="divide-y divide-gray-200 border-y border-gray-200">
                                    <li class="flex items-center gap-x-3 py-1.5 text-[11px] tracking-wide text-gray-400">
                                        <span class="min-w-0 flex-1">{{ ctrans('Material') }}</span>
                                        <span class="w-24 shrink-0">{{ ctrans('Quantity') }}</span>
                                        <span class="w-12 shrink-0">{{ ctrans('Unit') }}</span>
                                        <span class="w-8 shrink-0"></span>
                                    </li>
                                    <li v-for="(material, materialIndex) in editableMaterials(step)!" :key="material.raw_material_id" class="flex items-center gap-x-3 py-1.5 text-xs text-gray-600">
                                        <div class="min-w-0 flex-1 truncate" :title="material.description">
                                            <div class="font-bold">{{ material.code }}</div>
                                            <div class="opacity-70">{{ material.description }}</div>
                                        </div>
                                        <InputNumber
                                            v-model="material.quantity_per_unit"
                                            :min="0.0001"
                                            :minFractionDigits="0"
                                            :maxFractionDigits="4"
                                            :useGrouping="false"
                                            size="small"
                                            inputClass="w-24 text-xs" />
                                        <span class="w-12 shrink-0 text-gray-500">{{ material.unit }}</span>
                                        <Button type="transparent" size="xxs" icon="fal fa-trash-alt" :tooltip="ctrans('Remove raw material from step')" @click="removeRawMaterial(editableMaterials(step)!, materialIndex)" />
                                    </li>
                                </ul>
    
                                <div class="mt-3 flex flex-wrap items-end gap-x-3 gap-y-2">
                                    <label class="block">
                                        <span class="mb-1 block text-xs text-gray-500">{{ ctrans('Raw material') }}</span>
                                        <div class="w-64">
                                            <PureSelectInfiniteScroll
                                                v-model="step.newRawMaterial"
                                                :fetchRoute="setRecipe.raw_material_options"
                                                :placeholder="ctrans('Select raw material')"
                                                :noOptionsText="ctrans('No raw materials yet')"
                                                labelProp="description"
                                                labelAdditionalProp="code"
                                                object />
                                        </div>
                                    </label>
    
                                    <label class="block">
                                        <span class="mb-1 block text-xs text-gray-500">{{ ctrans('Quantity') }}</span>
                                        <InputNumber
                                            v-model="step.newRawMaterialQuantity"
                                            :min="0.0001"
                                            :minFractionDigits="0"
                                            :maxFractionDigits="4"
                                            :useGrouping="false"
                                            size="small"
                                            inputClass="w-24 text-xs" />
                                    </label>
    
                                    <Button
                                        type="secondary"
                                        size="xs"
                                        icon="fal fa-plus"
                                        :label="ctrans('Add material')"
                                        :disabled="!step.newRawMaterial"
                                        @click="addRawMaterial(step)" />
                                </div>
    
                                <p v-if="!focusedArtefact && artefactsWithOwnMaterials(step).length" class="mt-2 text-xs text-gray-500">
                                    {{ ctrans('Not used by :artefacts, which have their own materials on this step.', { artefacts: artefactsWithOwnMaterials(step).map(artefactLabel).join(', ') }) }}
                                </p>
                            </template>
                        </section>
                    </article>
                </div>

                <div class="shrink-0 space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <Button type="dashed" icon="fal fa-plus" :label="ctrans('Add step')" @click="addStep" />
                        <Link
                            v-if="setRecipe.create_task_route"
                            :href="route(setRecipe.create_task_route.name, setRecipe.create_task_route.parameters)"
                            class="secondaryLink text-sm">
                            {{ ctrans('Task missing? Create a manufacture task') }}
                        </Link>
                    </div>
    
                    <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 text-amber-600" fixed-width aria-hidden="true" />
                            <div class="text-sm text-amber-900">
                                <p class="font-medium">
                                    {{ artefacts.length === 1
                                        ? ctrans('The existing steps of this artefact will be replaced with this one.')
                                        : ctrans('The existing steps of these :count artefacts will be replaced with this one.', { count: artefacts.length }) }}
                                </p>
                                <p class="mt-1">
                                    {{ ctrans('Steps not in this list are removed together with their raw materials. Steps already there take these units and raw materials. This cannot be undone.') }}
                                </p>
                                <label class="mt-2 flex cursor-pointer items-center gap-2 font-medium">
                                    <input v-model="acknowledged" type="checkbox" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500" />
                                    {{ ctrans('I understand the existing steps will be replaced') }}
                                </label>
                            </div>
                        </div>
                    </div>
    
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <span v-if="blockingReason" class="mr-auto text-xs text-gray-500">{{ blockingReason }}</span>
                        <Button type="tertiary" :label="ctrans('Cancel')" @click="emits('close')" />
                        <Button
                            type="primary"
                            :label="artefacts.length === 1 ? ctrans('Replace steps on 1 artefact') : ctrans('Replace steps on :count artefacts', { count: artefacts.length })"
                            :loading="processing"
                            :disabled="!canSave"
                            @click="save" />
                    </div>
                </div>
            </div>

            <aside class="md:flex md:min-h-0 md:w-64 md:shrink-0 md:flex-col">
                <div class="flex min-h-0 flex-col rounded-lg border border-gray-200 bg-gray-50 md:max-h-full">
                    <div class="shrink-0 border-b border-gray-200 px-3 py-2">
                        <div class="text-sm font-medium">
                            {{ artefacts.length === 1 ? ctrans('1 artefact selected') : ctrans(':count artefacts selected', { count: artefacts.length }) }}
                        </div>
                        <div class="mt-0.5 text-xs text-gray-500">{{ ctrans('Pick one to give it different raw materials.') }}</div>
                    </div>
                    <ul class="min-h-0 flex-1 divide-y divide-gray-100 overflow-y-auto text-sm [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300">
                        <li>
                            <button
                                type="button"
                                class="w-full px-3 py-2 text-left"
                                :class="focusedArtefactId === null ? 'theme-current' : 'hover:bg-gray-100'"
                                @click="focusedArtefactId = null">
                                <div class="font-medium">{{ ctrans('All artefacts') }}</div>
                                <div class="text-xs text-gray-500">{{ ctrans('Shared raw materials') }}</div>
                            </button>
                        </li>
                        <li v-for="artefact in artefacts" :key="artefact.id">
                            <button
                                type="button"
                                class="w-full px-3 py-1.5 text-left"
                                :class="focusedArtefactId === artefact.id ? 'theme-current' : 'hover:bg-gray-100'"
                                @click="focusedArtefactId = artefact.id">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ artefactLabel(artefact) }}</span>
                                    <span v-if="artefactHasOwnMaterials(artefact.id)" class="theme-soft ml-auto shrink-0 rounded px-1.5 py-0.5 text-[10px]">
                                        {{ ctrans('Own materials') }}
                                    </span>
                                </div>
                                <div v-if="artefact.name" class="truncate text-xs text-gray-500" :title="artefact.name">{{ artefact.name }}</div>
                            </button>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </Modal>
</template>

<style scoped>
.theme-soft {
    background-color: color-mix(in srgb, var(--theme-color-4) 14%, transparent);
    color: color-mix(in srgb, var(--theme-color-4) 60%, black);
}

.theme-current {
    background-color: color-mix(in srgb, var(--theme-color-4) 10%, white);
    box-shadow: inset 3px 0 0 var(--theme-color-4);
}
</style>
