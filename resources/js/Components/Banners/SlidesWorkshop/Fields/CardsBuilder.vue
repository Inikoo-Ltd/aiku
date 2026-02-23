<script setup lang="ts">
import { computed } from 'vue'
import {
    Button,
    Card,
    Divider,
    ToggleButton,
    InputText,
    Textarea,
    Slider,
    ColorPicker,
    RadioButton
} from 'primevue'

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['update:modelValue'])

/**
 * v-model proxy (NO WATCH)
 */
const cards = computed({
    get: () => props.modelValue || {},
    set: (val) => emit('update:modelValue', val)
})

const createDefaultCard = () => ({
    enabled: true,
    hideCard: false,
    title: '',
    subtitle: '',
    horizontal: 'center',
    vertical: 'middle',
    width: 600,
    padding: 30,
    radius: 10,
    opacity: 1,
    background: '#ffffff',
    shadow: true
})

const addCard = () => {
    const nextIndex = Object.keys(cards.value).length + 1
    const key = `card${nextIndex}`

    cards.value = {
        ...cards.value,
        [key]: createDefaultCard()
    }
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
        <Button label="Add Card" icon="pi pi-plus" @click="addCard" class="w-full" />

        <!-- EMPTY STATE -->
        <div v-if="Object.keys(cards).length === 0" class="text-center text-gray-400">
            No cards added yet
        </div>

        <!-- CARDS -->
        <Card v-for="(card, key) in cards" :key="key" class="shadow-2 border-round-xl">
            <template #title>
                <div class="flex justify-between items-center">
                    <span class="font-semibold">{{ key }}</span>
                    <Button icon="pi pi-trash" severity="danger" text @click="removeCard(key)" />
                </div>
            </template>

            <template #content>
                <div class="space-y-4">

                    <!-- ENABLE -->
                    <div class="flex gap-3">
                        <ToggleButton v-model="card.enabled" onLabel="Enabled" offLabel="Disabled" />
                        <ToggleButton v-model="card.hideCard" onLabel="Hide Background" offLabel="Show Background" />
                        <ToggleButton v-model="card.shadow" onLabel="Shadow On" offLabel="Shadow Off" />
                    </div>

                    <Divider />

                    <!-- TEXT -->
                    <div class="space-y-3">
                        <div>
                            <label class="block mb-1 font-medium">Title</label>
                            <InputText v-model="card.title" class="w-full" />
                        </div>

                        <div>
                            <label class="block mb-1 font-medium">Subtitle</label>
                            <Textarea v-model="card.subtitle" rows="3" class="w-full" />
                        </div>
                    </div>

                    <Divider />

                    <!-- SIZE CONTROLS -->
                    <div class="grid grid-cols-2 gap-4">

                        <div>
                            <label class="block mb-2">Width ({{ card.width }}px)</label>
                            <Slider v-model="card.width" :min="300" :max="1000" :step="10" />
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
                            <label class="block mb-2">Opacity ({{ card.opacity }})</label>
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
                    <div class="grid grid-cols-2 gap-4">

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

                </div>
            </template>
        </Card>

    </div>
</template>