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
            text: '', color: '#000000', align: 'center',
            vertical: 'middle', fontSize: 32, offsetX: 0,
            offsetY: 0
        }
    ],
    horizontal: 'center',
    vertical: 'middle',
    width: 100,
    height: 100,
    padding: 30,
    radius: 10,
    opacity: 1,
    background: '#999999',
    shadow: true,
    textAlign: 'center',
    textVertical: 'middle',
    titleColor: '#000000',
    subtitleColor: '#333333',
    offsetX: 0,
    offsetY: 0
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
            <Card v-for="(card, key) in cards" :key="key" class="shadow-2 border-round-xl">
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
                                    <InputText v-model="item.text" class="w-full" placeholder="Enter title" />

                                    <ColorPicker v-model="item.color" />

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                        <!-- Horizontal -->
                                        <div>
                                            <label class="block mb-1 text-sm font-semibold">Horizontal Position</label>

                                            <div class="flex gap-4 items-center">

                                                <div class="flex items-center gap-1">
                                                    <RadioButton v-model="item.align"
                                                        :inputId="`align-left-${key}-${index}`" value="left" />
                                                    <label :for="`align-left-${key}-${index}`" class="cursor-pointer">
                                                        Left
                                                    </label>
                                                </div>

                                                <div class="flex items-center gap-1">
                                                    <RadioButton v-model="item.align"
                                                        :inputId="`align-center-${key}-${index}`" value="center" />
                                                    <label :for="`align-center-${key}-${index}`" class="cursor-pointer">
                                                        Center
                                                    </label>
                                                </div>

                                                <div class="flex items-center gap-1">
                                                    <RadioButton v-model="item.align"
                                                        :inputId="`align-right-${key}-${index}`" value="right" />
                                                    <label :for="`align-right-${key}-${index}`" class="cursor-pointer">
                                                        Right
                                                    </label>
                                                </div>

                                            </div>
                                        </div>

                                        <!-- Vertical -->
                                        <div>
                                            <label class="block mb-1 text-sm font-semibold">Vertical Position</label>

                                            <div class="flex gap-4 items-center">

                                                <div class="flex items-center gap-1">
                                                    <RadioButton v-model="item.vertical"
                                                        :inputId="`vertical-top-${key}-${index}`" value="top" />
                                                    <label :for="`vertical-top-${key}-${index}`" class="cursor-pointer">
                                                        Top
                                                    </label>
                                                </div>

                                                <div class="flex items-center gap-1">
                                                    <RadioButton v-model="item.vertical"
                                                        :inputId="`vertical-middle-${key}-${index}`" value="middle" />
                                                    <label :for="`vertical-middle-${key}-${index}`"
                                                        class="cursor-pointer">
                                                        Middle
                                                    </label>
                                                </div>

                                                <div class="flex items-center gap-1">
                                                    <RadioButton v-model="item.vertical"
                                                        :inputId="`vertical-bottom-${key}-${index}`" value="bottom" />
                                                    <label :for="`vertical-bottom-${key}-${index}`"
                                                        class="cursor-pointer">
                                                        Bottom
                                                    </label>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="block text-sm mb-1">
                                                Font Size ({{ item.fontSize }}px)
                                            </label>
                                            <Slider v-model="item.fontSize" :min="12" :max="120" :step="1" />
                                        </div>

                                        <div>
                                            <label class="block text-sm mb-1">
                                                Adjust Horizontal Position ({{ item.offsetX }}px)
                                            </label>
                                            <Slider v-model="item.offsetX" :min="-500" :max="500" :step="1" />
                                        </div>

                                        <div>
                                            <label class="block text-sm mb-1">
                                                Adjust Vertical Position ({{ item.offsetY }}px)
                                            </label>
                                            <Slider v-model="item.offsetY" :min="-500" :max="500" :step="1" />
                                        </div>
                                    </div>

                                    <Button label="Remove Text" size="sm" :icon="faTrashAlt" type="red"
                                        @click="removeTitle(key, index)" v-if="card.titles.length > 1" />
                                </div>

                                <Button label="Add Text" type="primary" full icon="fas fa-plus"
                                    @click="addTitle(key)" />
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

    </div>
</template>