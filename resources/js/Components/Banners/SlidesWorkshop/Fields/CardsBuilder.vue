<script setup lang="ts">
import { computed } from 'vue'
import {
    Card,
    Divider,
    ToggleButton,
    InputText,
    Slider,
    RadioButton
} from 'primevue'
import { faTrashAlt } from '@fal'
import ColorPicker from './ColorPicker.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from 'laravel-vue-i18n'
import SideEditorInputHTML from '@/Components/CMS/Fields/SideEditorInputHTML.vue'

library.add(faTrashAlt)

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) }
})
console.log('model', props.modelValue)
const emit = defineEmits(['update:modelValue'])


const cards = computed({
    get: () => props.modelValue ?? {},
    set: (val) => {
        console.log('changed -> emit', val)
        emit('update:modelValue', val)
    }
})


const defaultButton = () => ({
    show: true,
    text: 'Shop Now',
    link: '',
    width: 'auto',
    customWidth: 200,
    align: 'center',
    paddingX: 20,
    paddingY: 10,
    bgColor: '#000000',
    textColor: '#ffffff',
    radius: 6
})

const createDefaultCard = () => ({
    enabled: true,
    hideCard: false,
    titles: [{ text: '<h3>NEW IN</h3><p>Nymph gemstone rings</p>' }],
    horizontal: 'center',
    vertical: 'middle',
    width: 50,
    height: 160,
    padding: 30,
    radius: 10,
    opacity: 1,
    background: '#FFFFFF',
    shadow: true,
    textAlign: 'center',
    textVertical: 'middle',
    titleColor: '#000000',
    subtitleColor: '#333333',
    offsetX: 0,
    offsetY: 0,
    button: defaultButton()
})


const updateCards = (newCards: any) => {
    cards.value = { ...newCards }
}

const updateCard = (key: string, patch: any) => {
    const newCards = { ...cards.value }

    newCards[key] = {
        ...newCards[key],
        ...patch
    }

    cards.value = newCards
}

const updateButton = (key: string, patch: any) => {
    const card = cards.value[key]

    updateCard(key, {
        button: {
            ...defaultButton(),
            ...(card.button || {}),
            ...patch
        }
    })
}

const updateTitle = (key: string, index: number, val: any) => {
    const newCards = { ...cards.value }
    const titles = [...(newCards[key].titles || [])]

    titles[index] = {
        ...titles[index],
        ...val
    }

    newCards[key].titles = titles
    cards.value = newCards
}


const addCard = () => {
    const newCards = { ...cards.value }

    let i = 1
    while (newCards[`card${i}`]) i++

    newCards[`card${i}`] = createDefaultCard()
    cards.value = newCards
}

const removeCard = (key: string) => {
    const newCards = { ...cards.value }
    delete newCards[key]
    cards.value = newCards
}

const addTitle = (key: string) => {
    const newCards = { ...cards.value }
    const titles = [...(newCards[key].titles || [])]

    titles.push({ text: '' })
    newCards[key].titles = titles
    cards.value = newCards
}

const removeTitle = (key: string, index: number) => {
    const newCards = { ...cards.value }
    const titles = [...(newCards[key].titles || [])]

    titles.splice(index, 1)
    newCards[key].titles = titles
    cards.value = newCards
}

const ensureButton = (card: any) => {
    if (!card.button || typeof card.button !== 'object') {
        card.button = defaultButton()
        return
    }

    // merge jika sebagian field hilang (data lama)
    card.button = {
        ...defaultButton(),
        ...card.button
    }
}

const normalizedCards = computed(() => {
    const obj = cards.value || {}

    Object.keys(obj).forEach(key => {
        ensureButton(obj[key])
    })

    return obj
})
</script>

<template>
    <div class="space-y-4">

        <!-- ADD -->
        <Button label="Add Card" type="primary" full icon="fas fa-plus" @click="addCard" class="w-full" />

        <!-- EMPTY -->
        <div v-if="Object.keys(cards ?? {}).length === 0" class="text-center text-gray-400">
            {{ trans("No cards added yet") }}
        </div>

        <div class="shadow-lg">

            <Card v-for="(card, key) in normalizedCards" :key="key" class="shadow-2 border-round-xl">

                <template #title>
                    <div class="flex justify-between items-center">
                        <span>{{ key }}</span>
                        <Button :icon="faTrashAlt" type="red" size="sm" @click="removeCard(key)" />
                    </div>
                </template>

                <template #content>
                    <div class="space-y-4">

                        <!-- ENABLE -->
                        <div class="flex gap-3">
                            <ToggleButton :modelValue="card.enabled" @update:modelValue="v => updateCard(key, { enabled: v })"
                                onLabel="Enabled Card" offLabel="Disabled Card" />

                            <ToggleButton :modelValue="card.hideCard"
                                @update:modelValue="v => updateCard(key, { hideCard: v })" onLabel="Hide Background"
                                offLabel="Show Background" />

                            <ToggleButton :modelValue="card.shadow" @update:modelValue="v => updateCard(key, { shadow: v })"
                                onLabel="Shadow On" offLabel="Shadow Off" />
                        </div>

                        <Divider />

                        <!-- SIZE -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div>
                                <label class="block mb-2">Width ({{ card.width }}%)</label>
                                <Slider :modelValue="card.width" @update:modelValue="v => updateCard(key, { width: v })"
                                    :min="10" :max="100" :step="10" />
                            </div>

                            <div>
                                <label class="block mb-2">Height ({{ card.height }}px)</label>
                                <Slider :modelValue="card.height" @update:modelValue="v => updateCard(key, { height: v })"
                                    :min="10" :max="800" :step="10" />
                            </div>

                            <div>
                                <label class="block mb-2">Padding ({{ card.padding }}px)</label>
                                <Slider :modelValue="card.padding" @update:modelValue="v => updateCard(key, { padding: v })"
                                    :min="10" :max="100" :step="5" />
                            </div>

                            <div>
                                <label class="block mb-2">Border Radius ({{ card.radius }}px)</label>
                                <Slider :modelValue="card.radius" @update:modelValue="v => updateCard(key, { radius: v })"
                                    :min="0" :max="50" />
                            </div>

                            <div>
                                <label class="block mb-2">Opacity ({{ card.opacity }})</label>
                                <Slider :modelValue="card.opacity" @update:modelValue="v => updateCard(key, { opacity: v })"
                                    :min="0" :max="1" :step="0.1" />
                            </div>

                        </div>

                        <Divider />

                        <!-- COLOR -->
                        <div>
                            <label class="block mb-2 font-medium">Background</label>
                            <ColorPicker :modelValue="card.background"
                                @update:modelValue="v => updateCard(key, { background: v })" />
                        </div>

                        <Divider />

                        <!-- POSITION -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div>
                                <label class="block mb-2 font-medium">Horizontal</label>
                                <div class="flex gap-3">
                                    <RadioButton :modelValue="card.horizontal" value="left"
                                        @update:modelValue="v => updateCard(key, { horizontal: v })" /><label>Left</label>
                                    <RadioButton :modelValue="card.horizontal" value="center"
                                        @update:modelValue="v => updateCard(key, { horizontal: v })" /><label>Center</label>
                                    <RadioButton :modelValue="card.horizontal" value="right"
                                        @update:modelValue="v => updateCard(key, { horizontal: v })" /><label>Right</label>
                                </div>
                            </div>

                            <div>
                                <label class="block mb-2 font-medium">Vertical</label>
                                <div class="flex gap-3">
                                    <RadioButton :modelValue="card.vertical" value="top"
                                        @update:modelValue="v => updateCard(key, { vertical: v })" /><label>Top</label>
                                    <RadioButton :modelValue="card.vertical" value="middle"
                                        @update:modelValue="v => updateCard(key, { vertical: v })" /><label>Middle</label>
                                    <RadioButton :modelValue="card.vertical" value="bottom"
                                        @update:modelValue="v => updateCard(key, { vertical: v })" /><label>Bottom</label>
                                </div>
                            </div>
                        </div>

                        <Divider />

                        <!-- OFFSET -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-2">Adjust Horizontal ({{ card.offsetX }}px)</label>
                                <Slider :modelValue="card.offsetX" @update:modelValue="v => updateCard(key, { offsetX: v })"
                                    :min="-800" :max="800" />
                            </div>
                            <div>
                                <label class="block mb-2">Adjust Vertical ({{ card.offsetY }}px)</label>
                                <Slider :modelValue="card.offsetY" @update:modelValue="v => updateCard(key, { offsetY: v })"  :min="-800" :max="800" />
                            </div>
                        </div>

                        <Divider />

                        <!-- TEXT -->
                        <div class="space-y-3">
                            <label class="block mb-2 font-medium">Texts</label>
                            <div v-for="(item, index) in (card.titles || [])" :key="index"
                                class="space-y-2 mb-4 border p-3 rounded">

                                <SideEditorInputHTML :modelValue="item.text"
                                    @update:modelValue="v => updateTitle(key, index, { text: v })" class="w-full" />
                            </div>
                        </div>

                        <Divider />

                        <!-- BUTTON -->
                        <div class="space-y-3">
                            <label class="block mb-2 font-medium">Button</label>

                            <ToggleButton :modelValue="card.button.show"
                                @update:modelValue="v => updateButton(key, { show: v })" onLabel="Button Visible"
                                offLabel="Button Hidden" />

                            <div v-if="card.button.show" class="space-y-4 border rounded p-3">

                                <div>
                                    <label class="block mb-2 font-medium">Button Text</label>
                                    <InputText :modelValue="card.button.text"
                                        @update:modelValue="v => updateButton(key, { text: v })" class="w-full" />
                                </div>

                                <div>
                                    <label class="block mb-2 font-medium">Button Link</label>
                                    <InputText :modelValue="card.button.link"
                                        @update:modelValue="v => updateButton(key, { link: v })" class="w-full" />
                                </div>

                                <div>
                                    <label class="block mb-2 font-medium">Align</label>
                                    <div class="flex gap-4">
                                        <RadioButton :modelValue="card.button.align" value="left"
                                            @update:modelValue="v => updateButton(key, { align: v })" /><label>Left</label>
                                        <RadioButton :modelValue="card.button.align" value="center"
                                            @update:modelValue="v => updateButton(key, { align: v })" /><label>Center</label>
                                        <RadioButton :modelValue="card.button.align" value="right"
                                            @update:modelValue="v => updateButton(key, { align: v })" /><label>Right</label>
                                    </div>
                                </div>

                                <div>
                                    <label class="block mb-2 font-medium">Width</label>
                                    <div class="flex gap-4">
                                        <RadioButton :modelValue="card.button.width" value="auto"
                                            @update:modelValue="v => updateButton(key, { width: v })" /><label>Auto</label>
                                        <RadioButton :modelValue="card.button.width" value="full"
                                            @update:modelValue="v => updateButton(key, { width: v })" /><label>Full</label>
                                        <RadioButton :modelValue="card.button.width" value="custom"
                                            @update:modelValue="v => updateButton(key, { width: v })" /><label>Custom</label>
                                    </div>
                                </div>

                                <div v-if="card.button.width === 'custom'">
                                    <label class="block mb-2">Custom Width ({{ card.button.customWidth }}px)</label>
                                    <Slider :modelValue="card.button.customWidth"
                                        @update:modelValue="v => updateButton(key, { customWidth: v })" :min="50"
                                        :max="600" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-2">Padding X</label>
                                        <Slider :modelValue="card.button.paddingX"
                                            @update:modelValue="v => updateButton(key, { paddingX: v })" :min="0" :max="80" />
                                    </div>
                                    <div>
                                        <label class="block mb-2">Padding Y</label>
                                        <Slider :modelValue="card.button.paddingY"
                                            @update:modelValue="v => updateButton(key, { paddingY: v })" :min="0" :max="60" />
                                    </div>
                                </div>

                                <div>
                                    <label class="block mb-2">Radius</label>
                                    <Slider :modelValue="card.button.radius"
                                        @update:modelValue="v => updateButton(key, { radius: v })" :min="0" :max="40" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-2">Background</label>
                                        <ColorPicker :modelValue="card.button.bgColor"
                                            @update:modelValue="v => updateButton(key, { bgColor: v })" />
                                    </div>
                                    <div>
                                        <label class="block mb-2">Text Color</label>
                                        <ColorPicker :modelValue="card.button.textColor"
                                            @update:modelValue="v => updateButton(key, { textColor: v })" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

        </div>
    </div>
</template>