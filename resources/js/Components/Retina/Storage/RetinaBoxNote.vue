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
import { useTruncate } from '../../../Composables/useTruncate'
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
        })
        props.noteData.note = noteModalValue.value
    } catch (error) {
        console.log(error)
        notify({
			title: "Failed",
			text: "Failed to update the note, try again.",
			type: "error",
		})
    }

    isSubmitNoteLoading.value = false
    isModalOpen.value = false
}

</script>

<template>
    <div class="relative w-full" >
        <!-- Section: Header -->
        <div class="mb-1 flex gap-x-1 lg:pr-0 justify-between ">
            <div>
                <FontAwesomeIcon icon='fas fa-sticky-note' class=' text-gray-500' fixed-width aria-hidden='true' />
                {{ noteData.label }}
            </div>

            <!-- Section: Actions -->
            <template v-if="noteData.editable">
                <!-- Icon: pencil (edit) -->
                <div v-if="noteData.note" @click="isModalOpen = true" v-tooltip="trans('Edit note')" class="group px-0.5 cursor-pointer w-fit h-5 flex items-center">
                    <FontAwesomeIcon icon='fas fa-pencil' size="xs" class='text-gray-500 group-hover:text-gray-700'
                        fixed-width aria-hidden='true'
                    />
                </div>

                <!-- Icon: Plus (add note) -->
                <div v-else="!noteData.note" @click="isModalOpen = true" class="h-5 aspect-square flex items-center justify-center cursor-pointer">
                    <FontAwesomeIcon v-tooltip="trans('Add note')" icon='far fa-plus' class='' fixed-width aria-hidden='true'
                    />
                </div>
            </template>

            <!-- Icon: Lock -->
            <div v-else v-tooltip="noteData.lockMessage || trans('This note is not editable')" class="h-5 flex items-center cursor-not-allowed">
                <FontAwesomeIcon icon='fas fa-lock' class='text-gray-400' fixed-width aria-hidden='true' />
            </div>
        </div>

        <!-- Section: Note -->
        <p @dblclick="noteData.editable ? isModalOpen = true : false"
            v-tooltip="noteData.editable ? trans('Double click to edit') : false"
            class="h-36 text-justify mx-auto items-center px-3 rounded-md py-2 ring-1 ring-gray-300  break-words"
            :class="noteData.editable ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-50 text-gray-500'"
        >
            <template v-if="noteData.note">{{ useTruncate(noteData.note, 200) }}</template>
          <!--   <span v-else class="italic select-none text-gray-400">
                {{ trans('No note added') }}
            </span> -->
        </p>
    </div>

    <Modal :isOpen="isModalOpen" @onClose="() => (isModalOpen = false, noteModalValue = noteData.note)" width="w-full max-w-2xl">
		<div class="min-h-72 max-h-96 px-2 overflow-auto">
            <div class="text-xl font-semibold mb-2">{{ noteData.label }}</div>
			<div class="relative">
                <div v-if="noteModalValue" @click="() => noteModalValue = ''" class="absolute top-1 right-1 text-red-400 hover:text-red-600 text-xxs cursor-pointer">
                    Clear
                </div>
                <PureTextarea v-model="noteModalValue" counter rows="6" @keydown.ctrl.enter="() => onSubmitNote()" maxLength="5000" />
            </div>

            <div class="flex justify-end gap-x-2 mt-3">
                <Button label="cancel" @click="() => (isModalOpen = false, noteModalValue = noteData.note)" :style="'tertiary'" />
                <Button label="Save" @click="() => onSubmitNote()" :loading="isSubmitNoteLoading" :disabled="noteModalValue == noteData.note" />
            </div>
		</div>
	</Modal>
</template>