<!--
  NotesDisplay Component - Reusable component for displaying notes with modal
  Usage: Import and use this component wherever you need to display notes
-->

<script setup lang="ts">
import { ref, computed } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStickyNote } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { useBasicColor } from '@/Composables/useColors'
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { DialogTitle } from "@headlessui/vue"

library.add(faStickyNote)

// Props definition with generic typing for flexibility
const props = defineProps<{
    item: any // The item that contains notes (could be DeliveryNote, Order, etc.)
    noteFields?: {
        shipping?: string
        customer?: string
        internal?: string
        public?: string
    }
    referenceField?: string // Field name for the reference/identifier
    customNoteTypes?: Array<{
        key: string
        field: string
        bgColor: string
        textColor: string
        title: string
    }>
}>()

// console.log(props);

// Default note field mappings
const defaultNoteFields = {
    shipping: 'shipping_notes',
    customer: 'customer_notes',
    internal: 'internal_notes',
    public: 'public_notes'
}

// Merge default with custom note fields
const noteFields = computed(() => ({
    ...defaultNoteFields,
    ...props.noteFields
}))

// Modal state
const isNotesModalOpen = ref(false)
const selectedNoteType = ref<string | null>(null)

// Fallback colors
const fallbackBgColor = '#f9fafb'
const fallbackColor = '#374151'

// Function to check if a specific note exists and is not empty
const hasNote = (noteContent: string) => {
    return noteContent && noteContent.trim() !== ''
}

// Default note type configurations
const defaultNoteTypeConfigs = {
    shipping: {
        bgColor: '#38bdf8',
        textColor: '#38bdf8',
        title: 'Delivery Instructions'
    },
    customer: {
        bgColor: '#ff7dbd',
        textColor: '#ff7dbd',
        title: 'Customer Notes'
    },
    internal: {
        bgColor: '#fcf4a3',
        textColor: '#fcf4a3',
        title: 'Private Notes'
    },
    public: {
        bgColor: '#94db84',
        textColor: '#94db84',
        title: 'Public Notes'
    }
}

// Function to get available note types with their properties
const getAvailableNoteTypes = computed(() => {
    const noteTypes = []

    // Use custom note types if provided, otherwise use default ones
    if (props.customNoteTypes) {
        props.customNoteTypes.forEach(customType => {
            const noteContent = props.item[customType.field]
            if (hasNote(noteContent)) {
                noteTypes.push({
                    type: customType.key,
                    field: customType.field,
                    content: noteContent,
                    bgColor: customType.bgColor,
                    textColor: customType.textColor,
                    title: customType.title
                })
            }
        })
    } else {
        // Default note types
        Object.entries(noteFields.value).forEach(([key, fieldName]) => {
            const noteContent = props.item[fieldName]
            if (hasNote(noteContent)) {
                const config = defaultNoteTypeConfigs[key] || {
                    bgColor: fallbackBgColor,
                    textColor: fallbackColor,
                    title: `${key.charAt(0).toUpperCase() + key.slice(1)} Notes`
                }

                noteTypes.push({
                    type: key,
                    field: fieldName,
                    content: noteContent,
                    bgColor: config.bgColor,
                    textColor: config.textColor,
                    title: config.title
                })
            }
        })
    }

    return noteTypes
})

// Function to get current note content based on selected type
const getCurrentNoteContent = computed(() => {
    if (!selectedNoteType.value) return null

    const noteType = getAvailableNoteTypes.value.find(
        type => type.type === selectedNoteType.value
    )

    return noteType || null
})

// Function to get item reference
const getItemReference = computed(() => {
    const referenceField = props.referenceField || 'reference'
    return props.item[referenceField] || 'N/A'
})

// Function to open notes modal for specific note type
const openNotesModal = (noteType: string) => {
    selectedNoteType.value = noteType
    isNotesModalOpen.value = true
}

// Function to close notes modal
const closeNotesModal = () => {
    isNotesModalOpen.value = false
    selectedNoteType.value = null
}
</script>

<template>
    <div class="flex gap-2 items-center">
        <!-- Individual Notes Icons - Show separate icon for each note type -->
        <template v-for="noteType in getAvailableNoteTypes" :key="noteType.type">
            <button @click="openNotesModal(noteType.type)" v-tooltip="`View ${noteType.title}`"
                class="hover:opacity-80 transition-all duration-200 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500 rounded">
                <FontAwesomeIcon :icon="faStickyNote" class="text-sm cursor-pointer" fixed-width aria-hidden="true"
                    :style="{
                        color: useBasicColor(noteType.bgColor) || noteType.textColor || fallbackColor
                    }" />
            </button>
        </template>
    </div>

    <!-- Notes Modal -->
    <Modal :isOpen="isNotesModalOpen" @close="closeNotesModal" width="w-full max-w-lg">
        <div class="w-full">
            <div class="flex items-center justify-between mb-4">
                <DialogTitle as="h3" class="text-lg font-semibold text-gray-900">
                    <FontAwesomeIcon :icon="faStickyNote" class="mr-2 text-gray-600" fixed-width />
                    {{ trans('Note Details') }}
                </DialogTitle>
                <div class="text-sm text-gray-500">
                    {{ getItemReference }}
                </div>
            </div>

            <div v-if="getCurrentNoteContent" class="space-y-4">
                <div class="border-l-4 p-4 rounded-r-md" :style="{
                    borderLeftColor: getCurrentNoteContent.bgColor,
                    backgroundColor: getCurrentNoteContent.bgColor + (getCurrentNoteContent.bgColor === '#fcf4a3' ? '40' : '20')
                }">
                    <div class="flex items-center mb-2">
                        <FontAwesomeIcon :icon="faStickyNote" class="mr-2"
                            :style="{ color: getCurrentNoteContent.textColor }" fixed-width />
                        <h4 class="font-medium text-gray-900">
                            {{ trans(getCurrentNoteContent.title) }}
                        </h4>
                    </div>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                        {{ getCurrentNoteContent.content }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <Button type="secondary" :label="trans('Close')" @click="closeNotesModal" />
            </div>
        </div>
    </Modal>
</template>