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
    <div class="w-full min-w-0">
        <!-- File Upload Area -->
        <label
            :for="`file-upload-${fieldName}`"
            @drop.prevent="handleDrop"
            @dragover.prevent="handleDragOver"
            @dragleave="handleDragLeave"
            :class="[
                'relative flex w-full min-w-0 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed px-4 py-6 transition-colors focus-within:outline-none focus-within:ring-2 focus-within:ring-org-500 focus-within:ring-offset-2',
                isDragging ? 'border-org-500 bg-org-50' : 'border-gray-300 hover:border-gray-400',
                form.errors[fieldName] ? 'border-red-300 bg-red-50' : ''
            ]"
        >
            <div class="flex w-full min-w-0 flex-col items-center text-center space-y-2">
                <!-- Icon -->
                <FontAwesomeIcon
                    :icon="fileName ? ['fas', 'file'] : ['fad', 'arrow-up']"
                    class="h-10 w-10 shrink-0 text-gray-400"
                    aria-hidden="true"
                />

                <!-- Upload Text -->
                <div class="flex w-full min-w-0 flex-wrap items-baseline justify-center gap-x-1 text-sm text-gray-600">
                    <span class="block min-w-0 max-w-full truncate font-medium text-org-600">
                        {{ fileName || 'Upload a file' }}
                    </span>
                    <p v-if="!fileName" class="whitespace-nowrap">or drag and drop</p>
                </div>

                <!-- File Type Info -->
                <p v-if="fieldData?.accept" class="w-full break-words text-xs text-gray-500">
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

            <input
                :id="`file-upload-${fieldName}`"
                :name="fieldName"
                type="file"
                class="sr-only"
                @change="handleFileInput"
                :accept="fieldData?.accept"
            />
        </label>

        <!-- Error Message -->
        <div v-if="props.form.errors[props.fieldName]" class="mt-2 break-words text-sm text-red-600">
            {{ props.form.errors[props.fieldName] }}
        </div>

        <!-- Success Message -->
        <div v-if="fileName && !form.errors[fieldName]" class="mt-2 flex min-w-0 items-start gap-1 text-sm text-gray-600">
            <FontAwesomeIcon icon="fas fa-check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
            <span class="min-w-0 break-all">File selected: <span class="font-medium">{{ fileName }}</span></span>
        </div>

        <!-- Existing File Preview -->
        <div v-if="fieldData?.preview_url" class="mt-2 break-words text-sm">
            <a
                :href="fieldData.preview_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-org-600 hover:text-org-500 underline"
            >
                {{ fieldData?.preview_label || 'Preview current file' }}
            </a>
        </div>
    </div>
</template>
