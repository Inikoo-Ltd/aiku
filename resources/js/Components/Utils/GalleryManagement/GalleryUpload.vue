<script setup lang="ts">
import FileUpload from 'primevue/fileupload'
import Badge from 'primevue/badge'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUpload, faImages } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import ProgressBar from 'primevue/progressbar';


library.add(faUpload, faImages)

const fileUploadRef = ref();

const props = defineProps<{
    isLoading: boolean,
    multiple: Boolean,
    fileLimit?: number,
    accept: String,
    name: String,
    uploadProgress?: Number
}>()

const emits = defineEmits<{
    (e: 'onSubmitUpload', files: File[]): void
}>()

const formatSize = (bytes: number) => {
    const kb = bytes / 1024;
    const mb = kb / 1024;
    const gb = mb / 1024;

    if (gb >= 0.95) {
        return `${gb.toFixed(2)} GB`;
    } else if (mb >= 1) {
        return `${mb.toFixed(2)} MB`;
    } else {
        return `${kb.toFixed(2)} KB`;
    }
}

// Automatically trigger upload after selecting files
const handleFileSelection = (event: any) => {
    fileUploadRef.value.upload(); 
    emits('onSubmitUpload', event.files);
}


defineExpose({
    fileUploadRef
})

</script>

<template>
    <div class="relative">
        <FileUpload ref="fileUploadRef" v-bind="props" @select="handleFileSelection">
            <template #header="{ chooseCallback, clearCallback, files, uploadedFiles, uploadCallback }">
                <div class="flex flex-wrap justify-center items-center flex-1 gap-4">
                    <Button @click="() => { chooseCallback(); fileUploadRef.upload(); }" label="Choose & Upload" icon="fal fa-images" type="tertiary" />
                    <Button @click="() => {clearCallback(), uploadCallback()}" label="Clear" type="negative" :disabled="files.length === 0" />
                </div>
            </template>

            <template #content="{ files, uploadedFiles, removeUploadedFileCallback, removeFileCallback }">
                <ProgressBar v-if="uploadProgress" :value="uploadProgress" :showValue="true" style="height: 1rem;"> </ProgressBar>
                <div class="flex flex-col gap-8 pt-4">
                    <div v-if="files.length > 0">
                        <div class="flex flex-wrap gap-4">
                            <div v-for="(file, index) of files" :key="file.name + file.type + file.size"
                                class="p-8 rounded-border flex flex-col border border-surface items-center gap-4">
                                <div>
                                    <img role="presentation" :alt="file.name" :src="file.objectURL" width="100"
                                        height="50" />
                                </div>
                                <span class="font-semibold text-ellipsis max-w-60 whitespace-nowrap overflow-hidden">{{
                                    file.name }}</span>
                                <div>{{ formatSize(file.size) }}</div>
                                <Badge value="Pending" severity="warn" />
                                <Button icon="fal fa-times" type="negative" label=""
                                    @click="removeFileCallback(index)" />
                            </div>
                        </div>
                    </div>

                    <div v-if="uploadedFiles.length > 0">
                        <div class="flex flex-wrap gap-4">
                            <div v-for="(file, index) of uploadedFiles" :key="file.name + file.type + file.size"
                                class="p-8 rounded-border flex flex-col border border-surface items-center gap-4">
                                <div>
                                    <img role="presentation" :alt="file.name" :src="file.objectURL" width="100"
                                        height="50" />
                                </div>
                                <span class="font-semibold text-ellipsis max-w-60 whitespace-nowrap overflow-hidden">
                                    {{ file.name }}
                                </span>
                                <div>{{ formatSize(file.size) }}</div>
                                <Badge value="Completed" class="mt-4" severity="success" />
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #empty>
                <div class="flex items-center justify-center flex-col">
                    <FontAwesomeIcon icon='fal fa-upload'
                        class='!border-2 !rounded-full !p-8 !text-4xl !text-muted-color' fixed-width
                        aria-hidden='true' />
                    <p class="mt-6 mb-0">Drag and drop files to here to upload.</p>
                </div>
            </template>
        </FileUpload>
    </div>
</template>
