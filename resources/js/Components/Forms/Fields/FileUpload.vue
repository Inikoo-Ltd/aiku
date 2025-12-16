<script setup lang="ts">
import { ref } from "vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationCircle, faCheckCircle, faFile } from '@fas'
import { faSpinnerThird, faArrowUp } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faSpinnerThird, faExclamationCircle, faCheckCircle, faArrowUp, faFile)

const props = defineProps(['form', 'fieldName', 'options', 'fieldData'])

const fileName = ref<string>('')
const isDragging = ref(false)

const fileUploaded = (file: File | null) => {
    if (!file || !(file instanceof File)) {
        return
    }

    props.form[props.fieldName] = file
    fileName.value = file.name
}

const handleFileInput = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files && target.files[0]) {
        fileUploaded(target.files[0])
    }
}

const handleDrop = (event: DragEvent) => {
    isDragging.value = false
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        fileUploaded(event.dataTransfer.files[0])
    }
}

const handleDragOver = (event: DragEvent) => {
    event.preventDefault()
    isDragging.value = true
}

const handleDragLeave = () => {
    isDragging.value = false
}

</script>

<template>
    <div class="w-full">
        <!-- File Upload Area -->
        <div
            @drop.prevent="handleDrop"
            @dragover.prevent="handleDragOver"
            @dragleave="handleDragLeave"
            :class="[
                'relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-8 transition-colors',
                isDragging ? 'border-org-500 bg-org-50' : 'border-gray-300 hover:border-gray-400',
                form.errors[fieldName] ? 'border-red-300 bg-red-50' : ''
            ]"
        >
            <div class="flex flex-col items-center text-center space-y-2">
                <!-- Icon -->
                <FontAwesomeIcon
                    :icon="fileName ? ['fas', 'file'] : ['fad', 'arrow-up']"
                    class="h-10 w-10 text-gray-400"
                    aria-hidden="true"
                />

                <!-- Upload Text -->
                <div class="flex text-sm text-gray-600">
                    <label
                        :for="`file-upload-${fieldName}`"
                        class="relative cursor-pointer rounded-md font-medium text-org-600 hover:text-org-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-org-500 focus-within:ring-offset-2"
                    >
                        <span>{{ fileName || 'Upload a file' }}</span>
                        <input
                            :id="`file-upload-${fieldName}`"
                            :name="fieldName"
                            type="file"
                            class="sr-only"
                            @change="handleFileInput"
                            :accept="fieldData?.accept"
                        />
                    </label>
                    <p v-if="!fileName" class="pl-1">or drag and drop</p>
                </div>

                <!-- File Type Info -->
                <p v-if="fieldData?.accept" class="text-xs text-gray-500">
                    {{ fieldData.accept }}
                </p>
            </div>

            <!-- Status Icons -->
            <div class="absolute top-2 right-2 flex items-center pointer-events-none">
                <FontAwesomeIcon
                    v-if="form.errors[fieldName]"
                    icon="fas fa-exclamation-circle"
                    class="h-5 w-5 text-red-500"
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    v-if="form.recentlySuccessful"
                    icon="fas fa-check-circle"
                    class="h-5 w-5 text-green-500"
                    aria-hidden="true"
                />
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="props.form.errors[props.fieldName]" class="mt-2 text-sm text-red-600">
            {{ props.form.errors[props.fieldName] }}
        </div>

        <!-- Success Message -->
        <div v-if="fileName && !form.errors[fieldName]" class="mt-2 text-sm text-gray-600">
            <FontAwesomeIcon icon="fas fa-check-circle" class="h-4 w-4 text-green-500 inline mr-1" />
            File selected: <span class="font-medium">{{ fileName }}</span>
        </div>
    </div>
</template>
