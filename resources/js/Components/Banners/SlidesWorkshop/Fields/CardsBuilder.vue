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
    modelValue: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['update:modelValue'])

const cards = computed({
    get: () => props.modelValue ?? {},
    set: (val) => emit('update:modelValue', val)
})

const createDefaultCard = () => ({
    enabled: true,
    hideCard: false,
    titles: [
        {
            text: '<h3>NEW IN</h3><p>Nymph gemstone rings</p>'
        }
    ],
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
    button: {
        show: true,
        text: 'Shop Now',
        link: '',
        width: 'auto', // auto | full | custom
        customWidth: 200,
        align: 'center', // left | center | right
        paddingX: 20,
        paddingY: 10,
        bgColor: '#000000',
        textColor: '#ffffff',
        radius: 6
    }
})

const addTitle = (key: string) => {
    if (!cards.value[key].titles) {
        cards.value[key].titles = []
    }

    cards.value[key].titles.push({
        text: '',
        color: '#000000',
        align: 'center',
        vertical: 'middle',
        fontSize: 32,
        offsetX: 0,
        offsetY: 0
    })
}

const removeTitle = (cardKey: string, index: number) => {
    cards.value[cardKey].titles.splice(index, 1)
}

const addCard = () => {
    const safeCards = { ...(cards.value || {}) }

    let index = 1
    while (safeCards[`card${index}`]) {
        index++
    }

    safeCards[`card${index}`] = createDefaultCard()

    cards.value = safeCards
}

const removeCard = (key: string) => {
    const newCards = { ...cards.value }
    delete newCards[key]
    cards.value = newCards
}

const defaultButton = () => ({
    show: true,
    text: 'Shop Now',
    link: '',
    width: 'auto', // auto | full | custom
    customWidth: 200,
    align: 'center', // left | center | right
    paddingX: 20,
    paddingY: 10,
    bgColor: '#000000',
    textColor: '#ffffff',
    radius: 6
})

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

        <!-- ADD BUTTON -->
        <Button label="Add Card" type="primary" full icon="fas fa-plus" @click="addCard" class="w-full" />

        <!-- EMPTY STATE -->
        <div v-if="Object.keys(cards ?? {}).length === 0" class="text-center text-gray-400">
            {{ trans("No cards added yet") }}
        </div>

        <!-- CARDS -->
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
                            <ToggleButton v-model="card.enabled" onLabel="Enabled Card" offLabel="Disabled Card" />
                            <ToggleButton v-model="card.hideCard" onLabel="Hide Background"
                                offLabel="Show Background" />
                            <ToggleButton v-model="card.shadow" onLabel="Shadow On" offLabel="Shadow Off" />
                        </div>

                        <Divider />

                        <!-- SIZE CONTROLS -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div>
                                <label class="block mb-2">Width ({{ card.width }}%)</label>
                                <Slider v-model="card.width" :min="10" :max="100" :step="10" />
                            </div>

                            <div>
                                <label class="block mb-2">Height ({{ card.height }}px)</label>
                                <Slider v-model="card.height" :min="10" :max="800" :step="10" />
                            </div>

                            <div>
                                <label class="block mb-2">Padding ({{ card.padding }}px)</label>
                                <Slider v-model="card.padding" :min="10" :max="100" :step="5" />
                            </div>

                            <div>
                                <label class="block mb-2">Border Radius ({{ card.radius }}px)</label>
                                <Slider v-model="card.radius" :min="0" :max="50" :step="1" />
                            </div>

                            <div>
                                <label class="block mb-2">Opacity/Transparency ({{ card.opacity }})</label>
                                <Slider v-model="card.opacity" :min="0" :max="1" :step="0.1" />
                            </div>

                        </div>

                        <Divider />

                        <!-- COLOR -->
                        <div>
                            <label class="block mb-2 font-medium">Background</label>
                            <ColorPicker v-model="card.background" />
                        </div>

                        <Divider />

                        <!-- POSITION -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div>
                                <label class="block mb-2 font-medium">Horizontal</label>
                                <div class="flex gap-3">
                                    <RadioButton v-model="card.horizontal" value="left" />
                                    <label>Left</label>

                                    <RadioButton v-model="card.horizontal" value="center" />
                                    <label>Center</label>

                                    <RadioButton v-model="card.horizontal" value="right" />
                                    <label>Right</label>
                                </div>
                            </div>

                            <div>
                                <label class="block mb-2 font-medium">Vertical</label>
                                <div class="flex gap-3">
                                    <RadioButton v-model="card.vertical" value="top" />
                                    <label>Top</label>

                                    <RadioButton v-model="card.vertical" value="middle" />
                                    <label>Middle</label>

                                    <RadioButton v-model="card.vertical" value="bottom" />
                                    <label>Bottom</label>
                                </div>
                            </div>

                        </div>

                        <Divider />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div>
                                <label class="block mb-2">
                                    Adjust Horizontal Position Card ({{ card.offsetX }}px)
                                </label>
                                <Slider v-model="card.offsetX" :min="-800" :max="800" :step="1" />
                            </div>

                            <div>
                                <label class="block mb-2">
                                    Adjust Vertical Position Card ({{ card.offsetY }}px)
                                </label>
                                <Slider v-model="card.offsetY" :min="-800" :max="800" :step="1" />
                            </div>

                        </div>

                        <Divider />
                        <!-- TEXT -->
                        <div class="space-y-3">
                            <div>
                                <label class="block mb-2 font-medium">Texts</label>
                                <div v-for="(item, index) in (card.titles || [])" :key="index"
                                    class="space-y-2 mb-4 border p-3 rounded">
                                    <SideEditorInputHTML v-model="item.text" class="w-full" placeholder="Enter title" />
                                </div>
                            </div>
                        </div>

                        <Divider />

                        <!-- BUTTON SETTINGS -->
                        <div class="space-y-3">
                            <label class="block mb-2 font-medium">Button</label>
                            <div class="flex items-center gap-3">
                                <ToggleButton v-model="card.button.show" onLabel="Button Visible"
                                    offLabel="Button Hidden" />
                            </div>

                            <div v-if="card.button.show" class="space-y-4 border rounded p-3">

                                <!-- TEXT -->
                                <div>
                                    <label class="block mb-2 font-medium">Button Text</label>
                                    <InputText v-model="card.button.text" class="w-full" />
                                </div>

                                <!-- LINK -->
                                <div>
                                    <label class="block mb-2 font-medium">Button Link</label>
                                    <InputText v-model="card.button.link" class="w-full" placeholder="https://..." />
                                </div>

                                <!-- ALIGN -->
                                <div>
                                    <label class="block mb-2 font-medium">Button Align</label>
                                    <div class="flex gap-4">
                                        <div class="flex items-center gap-2">
                                            <RadioButton v-model="card.button.align" value="left" />
                                            <label>Left</label>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <RadioButton v-model="card.button.align" value="center" />
                                            <label>Center</label>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <RadioButton v-model="card.button.align" value="right" />
                                            <label>Right</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- WIDTH MODE -->
                                <div>
                                    <label class="block mb-2 font-medium">Button Width</label>
                                    <div class="flex gap-4">
                                        <div class="flex items-center gap-2">
                                            <RadioButton v-model="card.button.width" value="auto" />
                                            <label>Auto</label>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <RadioButton v-model="card.button.width" value="full" />
                                            <label>Full</label>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <RadioButton v-model="card.button.width" value="custom" />
                                            <label>Custom</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- CUSTOM WIDTH -->
                                <div v-if="card.button.width === 'custom'">
                                    <label class="block mb-2">
                                        Custom Width ({{ card.button.customWidth }}px)
                                    </label>
                                    <Slider v-model="card.button.customWidth" :min="50" :max="600" />
                                </div>

                                <!-- PADDING -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-2">
                                            Padding X ({{ card.button.paddingX }}px)
                                        </label>
                                        <Slider v-model="card.button.paddingX" :min="0" :max="80" />
                                    </div>

                                    <div>
                                        <label class="block mb-2">
                                            Padding Y ({{ card.button.paddingY }}px)
                                        </label>
                                        <Slider v-model="card.button.paddingY" :min="0" :max="60" />
                                    </div>
                                </div>

                                <!-- RADIUS -->
                                <div>
                                    <label class="block mb-2">
                                        Border Radius ({{ card.button.radius }}px)
                                    </label>
                                    <Slider v-model="card.button.radius" :min="0" :max="40" />
                                </div>

                                <!-- COLORS -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-2 font-medium">Background</label>
                                        <ColorPicker v-model="card.button.bgColor" />
                                    </div>

                                    <div>
                                        <label class="block mb-2 font-medium">Text Color</label>
                                        <ColorPicker v-model="card.button.textColor" />
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