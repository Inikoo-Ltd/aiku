<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { inject, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPencil, faStickyNote, faTrash, faLock } from '@fas'
import { faTimes } from '@fal'
import { faPlus } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import Modal from '@/Components/Utils/Modal.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import axios from 'axios'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { PDRNotes } from '@/types/Pallet'
// import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useBasicColor } from '@/Composables/useColors'
import InformationIcon from '../Utils/InformationIcon.vue'
library.add(faPencil, faStickyNote, faTrash, faPlus, faLock, faTimes)

// const layout = inject('layout', layoutStructure)

const props = defineProps<{
    noteData: PDRNotes
    updateRoute: routeType
}>()

// Section: Modal Note
const noteModalValue = ref(props.noteData.note)
const isModalOpen = ref(false)
const isSubmitNoteLoading = ref(false)
const onSubmitNote = async () => {
    isSubmitNoteLoading.value = true
    try {
        const response = await axios.patch(route(props.updateRoute.name, props.updateRoute.parameters), {
            [props.noteData.field]: noteModalValue.value
        },
        {
                headers: { "Content-Type": 'application/json' },
        }
        )
        props.noteData.note = noteModalValue.value
    } catch (error) {
        notify({
			title: "Failed",
			text: "Failed to update the note, try again.",
			type: "error",
		})
    }

    isSubmitNoteLoading.value = false
    isModalOpen.value = false
}

const fallbackBgColor = '#f9fafb'  // Background
const fallbackColor = '#374151'  // Color
</script>

<template>
    <div class="relative w-full pt-4" >
        <!-- Section: Header -->
        <div class="absolute top-0 left-0 w-full flex gap-x-1 lg:pr-0 justify-between lg:justify-normal">
            <div class="w-full flex items-center justify-between text-xs truncate text-center py-0.5 pl-3 pr-3" :style="{
                // borderBottom: `color-mix(in srgb, ${useBasicColor(noteData.bgColor)} 80%, black) solid 1px`,
                backgroundColor: useBasicColor(noteData.bgColor) ? `color-mix(in srgb, ${useBasicColor(noteData.bgColor)} 40%, white)` : noteData.bgColor || fallbackBgColor,
                color: noteData.color || fallbackColor
            }">
                <div
                    :style="{
                        color: noteData.textColor || fallbackColor
                    }"
                >
                    <FontAwesomeIcon icon='fas fa-sticky-note' class='' fixed-width aria-hidden='true' />
                    {{ noteData.label }}
                    <InformationIcon v-if="noteData.information" :information="noteData.information" />
                </div>

                <!-- Section: Actions -->
                <template v-if="noteData.editable">
                    <!-- Icon: pencil (edit) -->
                    <div v-if="noteData.note" @click="isModalOpen = true" v-tooltip="trans('Edit note')" class="group px-0.5 cursor-pointer w-fit h-5 flex items-center">
                        <FontAwesomeIcon icon='fas fa-pencil' size="xs" class='group-hover:text-gray-100'
                            fixed-width aria-hidden='true'
                            :style="{
                                color: noteData.textColor || fallbackColor
                            }"
                        />
                    </div>

                    <!-- Icon: Plus (add note) -->
                    <div v-else="!noteData.note" @click="isModalOpen = true" class="h-5 aspect-square flex items-center justify-center cursor-pointer">
                        <FontAwesomeIcon v-tooltip="trans('Add note')" icon='far fa-plus' class='' fixed-width aria-hidden='true'
                        :style="{
                            color: noteData.textColor || fallbackColor
                        }"
                    />
                    </div>
                </template>

                <!-- Icon: Lock -->
                <div v-else v-tooltip="noteData.lockMessage || trans('This note is not editable')" class="h-5 flex items-center cursor-not-allowed">
                    <FontAwesomeIcon icon='fas fa-lock' class='text-gray-400' fixed-width aria-hidden='true' />
                </div>
            </div>
        </div>

        <!-- Section: Note -->
        <p @dblclick="noteData.editable ? isModalOpen = true : false"
            v-tooltip="noteData.editable ? trans('Double click to edit') : false"
            class="h-full max-h-32 mx-auto items-center px-4 rounded-md pt-4 pb-2 text-xxs break-words"
            :class="noteData.editable ? 'cursor-pointer' : ''"
            :style="{
                backgroundColor: useBasicColor(noteData.bgColor) + '11' || noteData.bgColor ? `${noteData.bgColor}11` : fallbackBgColor,
                color: fallbackColor,
                border:`1px solid ${useBasicColor(noteData.bgColor) ? `color-mix(in srgb, ${useBasicColor(noteData.bgColor)} 40%, white)` : noteData.bgColor || fallbackBgColor}`
            }"
        >
            <template v-if="noteData.note">{{ noteData.note }}</template>
            <span v-else class="italic select-none"
                :style="{
                    color: fallbackColor + '55'
                }"
            >
                {{ trans('No note added') }}
            </span>
        </p>
    </div>

    <Modal :isOpen="isModalOpen" @onClose="() => (isModalOpen = false, noteModalValue = noteData.note)" width="w-[600px]">
		<div class="min-h-64 max-h-96 px-2 overflow-auto flex flex-col justify-between">
            <div>
                <div class="text-xl font-semibold mb-2">{{ noteData.label }} {{ trans("note") }}</div>
                            <div class="relative isolate">
                    <div v-if="noteModalValue" @click="() => noteModalValue = ''" class="z-10 absolute top-1 right-1 text-red-400 hover:text-red-600 text-xxs cursor-pointer">
                        {{ trans("Clear") }}
                    </div>
                    <PureTextarea v-model="noteModalValue" counter :rows="6" @keydown.ctrl.enter="() => onSubmitNote()" maxLength="5000" />
                </div>
            </div>

            <div class="flex justify-end gap-x-2 mt-7">
                <Button label="cancel" @click="() => (isModalOpen = false, noteModalValue = noteData.note)" :style="'tertiary'" />
                <Button label="Save (Ctrl + enter)" full @click="() => onSubmitNote()" :loading="isSubmitNoteLoading" :disabled="noteModalValue == noteData.note" />
            </div>
		</div>
	</Modal>
</template>