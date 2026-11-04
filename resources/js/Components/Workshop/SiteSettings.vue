<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { cloneDeep, debounce, get, set } from 'lodash-es'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSyncAlt, faTimes } from '@fal'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import FontNotFor from '@/Components/Workshop/Properties/FontNotFor.vue'
import { fontsForCurrentShop } from '@/Composables/useFont'
import { ctrans, ctransChoice } from '@/Composables/useTrans'
import { getBlueprint } from '@/Composables/getBlueprintWorkshop'
import {
    applyPageStyleToBlock,
    changedPageStyleFields,
    filledPageStyleFields,
    PageStyle,
    PageStyleChange,
    PageStyleField,
    usesWebsiteLayout,
} from '@/Composables/usePageStyle'
import { Root as RootWebpage } from '@/types/webpageTypes'
import type { ColorValue } from '@/Components/Utils/colorValue'

library.add(faSyncAlt, faTimes)

const props = withDefaults(defineProps<{
    webpage: RootWebpage & {
        page_style?: PageStyle | []
        update_page_style_route?: { name: string, parameters: number }
    }
    editable?: boolean
}>(), { editable: true })

const emits = defineEmits<{
    (e: 'pageStyleSaved', webpage: object): void
    (e: 'pageStyleSaving', isSaving: boolean): void
}>()

const emptyStyle = (): PageStyle => ({
    colors: { text: null, background: null, primary: null, button_text: null },
    font_family: null,
    button: { radius: null },
    spacing: { vertical: null, horizontal: null },
})

const initialStyle = (): PageStyle => {
    const style = emptyStyle()
    const saved = Array.isArray(props.webpage.page_style) ? {} : (props.webpage.page_style ?? {})
    for (const field of filledPageStyleFields(saved)) {
        set(style, field, get(saved, field))
    }
    return style
}

const style = ref<PageStyle>(initialStyle())
const saveError = ref<string | null>(null)

const fontOptions = computed(() => fontsForCurrentShop())

const colorFields: { field: PageStyleField, label: string, hint: string }[] = [
    { field: 'colors.primary', label: 'Primary', hint: 'Button background' },
    { field: 'colors.button_text', label: 'Button text', hint: 'Text on buttons' },
    { field: 'colors.text', label: 'Text', hint: 'Block text' },
    { field: 'colors.background', label: 'Background', hint: 'Block background, images are kept' },
]

const toRgba = (color: ColorValue | null) =>
    color ? `rgba(${color.rgba.r}, ${color.rgba.g}, ${color.rgba.b}, ${color.rgba.a})` : null

const pendingChanges = new Map<number, Map<string, PageStyleChange>>()
const isSaving = ref(false)
let isSaveQueued = false

const queueChanges = (blockId: number, changes: PageStyleChange[], keepNewer = false) => {
    if (!changes.length) {
        return
    }
    const blockChanges = pendingChanges.get(blockId) ?? new Map<string, PageStyleChange>()
    for (const change of changes) {
        const key = change.path.join('.')
        if (!keepNewer || !blockChanges.has(key)) {
            blockChanges.set(key, change)
        }
    }
    pendingChanges.set(blockId, blockChanges)
}

const takePendingChanges = () => {
    const webBlocks = [...pendingChanges].map(([id, changes]) => ({ id, changes: [...changes.values()] }))
    pendingChanges.clear()
    return webBlocks
}

const firstErrorMessage = (errors: Record<string, string>) =>
    Object.values(errors)[0] ?? ctrans('The page style could not be saved')

const savePageStyle = () => {
    if (!props.webpage.update_page_style_route) {
        return
    }
    if (isSaving.value) {
        isSaveQueued = true
        return
    }

    const webBlocks = takePendingChanges()
    isSaving.value = true
    emits('pageStyleSaving', true)

    router.patch(
        route(props.webpage.update_page_style_route.name, props.webpage.update_page_style_route.parameters),
        { page_style: style.value, web_blocks: webBlocks },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                saveError.value = null
                emits('pageStyleSaved', page.props.webpage as object)
            },
            onError: (errors) => {
                for (const webBlock of webBlocks) {
                    queueChanges(webBlock.id, webBlock.changes, true)
                }
                saveError.value = firstErrorMessage(errors)
            },
            onFinish: () => {
                isSaving.value = false
                emits('pageStyleSaving', false)
                if (isSaveQueued) {
                    isSaveQueued = false
                    savePageStyle()
                }
            },
        }
    )
}

const debouncedSavePageStyle = debounce(savePageStyle, 1000)

const applyToBlocks = (fields: PageStyleField[]) => {
    if (!fields.length) {
        return
    }
    for (const webBlock of props.webpage.layout.web_blocks as any[]) {
        const changes = applyPageStyleToBlock(webBlock, style.value, fields, getBlueprint(webBlock.type, props.webpage))
        queueChanges(webBlock.id, changes)
    }
}

const updateField = (field: PageStyleField, value: unknown) => {
    if (!props.editable) {
        return
    }
    const previous = cloneDeep(style.value)
    set(style.value, field, value === '' || value === undefined ? null : value)
    if (get(previous, field) === get(style.value, field)) {
        return
    }
    applyToBlocks(changedPageStyleFields(previous, style.value))
    debouncedSavePageStyle()
}

const reapplyAll = () => {
    if (!props.editable) {
        return
    }
    applyToBlocks(filledPageStyleFields(style.value))
    debouncedSavePageStyle()
}

const hasAnyStyle = computed(() => filledPageStyleFields(style.value).length > 0)

const websiteLayoutBlockCount = computed(() =>
    (props.webpage.layout.web_blocks as any[]).filter((webBlock) => usesWebsiteLayout(webBlock)).length
)
</script>

<template>
    <div class="flex flex-col gap-4 p-2 text-xs">
        <p class="text-gray-500 leading-snug">
            {{ ctrans('Choices here are written into every block on this page and replace the colour or font set on a block. Clearing a choice stops applying it; blocks keep what they have. Publish to show the changes on the website.') }}
        </p>

        <p v-if="websiteLayoutBlockCount" class="rounded-md border border-amber-200 bg-amber-50 p-2 leading-snug text-amber-800">
            {{ ctransChoice(':count block on this page follows the website template and is not changed here. Change it in the website layout.|:count blocks on this page follow the website template and are not changed here. Change them in the website layout.', websiteLayoutBlockCount) }}
        </p>

        <section>
            <h3 class="mb-2 font-semibold text-gray-700">{{ ctrans('Colours') }}</h3>
            <div class="flex flex-col gap-2">
                <div v-for="colorField in colorFields" :key="colorField.field" class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-gray-700">{{ ctrans(colorField.label) }}</div>
                        <div class="text-gray-400">{{ ctrans(colorField.hint) }}</div>
                    </div>
                    <div class="flex items-center gap-1">
                        <ColorPicker
                            :color="get(style, colorField.field)"
                            :isEditable="editable"
                            closeButton
                            @changeColor="(color) => updateField(colorField.field, toRgba(color))">
                            <template #button>
                                <div
                                    class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-gray-300 shadow-sm"
                                    :style="{ background: get(style, colorField.field) || 'transparent' }">
                                    <span v-if="!get(style, colorField.field)" class="text-gray-400">—</span>
                                </div>
                            </template>
                        </ColorPicker>
                        <button
                            v-if="get(style, colorField.field) && editable"
                            type="button"
                            class="h-6 w-6 rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            :title="ctrans('Stop applying')"
                            @click="updateField(colorField.field, null)">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h3 class="mb-2 font-semibold text-gray-700">{{ ctrans('Font') }}</h3>
            <Select
                :modelValue="style.font_family"
                :options="fontOptions"
                optionLabel="label"
                optionValue="value"
                :placeholder="ctrans('Keep block fonts')"
                :disabled="!editable"
                showClear
                class="w-full"
                size="small"
                @update:modelValue="(value) => updateField('font_family', value)">
                <template #option="{ option }">
                    <span :style="{ fontFamily: option.value }">
                        {{ option.label }}
                        <FontNotFor :languages="option.notFor" />
                    </span>
                </template>
            </Select>
        </section>

        <section>
            <h3 class="mb-2 font-semibold text-gray-700">{{ ctrans('Buttons') }}</h3>
            <label class="flex items-center justify-between gap-2">
                <span class="text-gray-700">{{ ctrans('Corner radius') }}</span>
                <InputNumber
                    :modelValue="style.button?.radius"
                    :min="0"
                    :max="100"
                    suffix=" px"
                    :disabled="!editable"
                    size="small"
                    inputClass="w-24"
                    @update:modelValue="(value) => updateField('button.radius', value)" />
            </label>
        </section>

        <section>
            <h3 class="mb-2 font-semibold text-gray-700">{{ ctrans('Layout') }}</h3>
            <div class="flex flex-col gap-2">
                <label class="flex items-center justify-between gap-2">
                    <span class="text-gray-700">{{ ctrans('Block spacing top and bottom') }}</span>
                    <InputNumber
                        :modelValue="style.spacing?.vertical"
                        :min="0"
                        :max="400"
                        suffix=" px"
                        :disabled="!editable"
                        size="small"
                        inputClass="w-24"
                        @update:modelValue="(value) => updateField('spacing.vertical', value)" />
                </label>
                <label class="flex items-center justify-between gap-2">
                    <span class="text-gray-700">{{ ctrans('Block spacing left and right') }}</span>
                    <InputNumber
                        :modelValue="style.spacing?.horizontal"
                        :min="0"
                        :max="400"
                        suffix=" px"
                        :disabled="!editable"
                        size="small"
                        inputClass="w-24"
                        @update:modelValue="(value) => updateField('spacing.horizontal', value)" />
                </label>
            </div>
        </section>

        <div v-if="editable && hasAnyStyle" class="flex flex-col gap-1">
            <Button
                type="tertiary"
                :label="ctrans('Apply again to all blocks')"
                :tooltip="ctrans('Use after adding blocks or changing a block by hand')"
                full
                @click="reapplyAll">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sync-alt" class="text-[var(--app-accent)]" fixed-width />
                </template>
            </Button>
        </div>

        <p v-if="saveError" class="text-red-600">{{ saveError }}</p>
    </div>
</template>
