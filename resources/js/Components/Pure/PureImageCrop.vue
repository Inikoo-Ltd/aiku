<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { ref, watch } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faUndoAlt } from "@fal";
import { faSpinnerThird, faExclamationCircle, faCheckCircle } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Cropper } from "vue-advanced-cropper";
import "vue-advanced-cropper/dist/style.css";
import Button from "@/Components/Elements/Buttons/Button.vue";

// ✅ PrimeVue Dialog
import Dialog from "primevue/dialog";

library.add(faSpinnerThird, faExclamationCircle, faCheckCircle, faUndoAlt);

const props = defineProps<{
    src_image?: string
    aspectRatio: number
}>();

const emits = defineEmits<{
    (e: "cropped", value: File): void
}>();

const numbKey = ref(0);
const tempImgToCrop = ref<string | null>(null);
const imageUrl = ref<string | null>(props.src_image ?? null);

const isOpenModalCrop = ref(false);
const _cropper = ref<InstanceType<typeof Cropper> | null>(null);

// pick file
const onPickFile = (file?: File) => {
    if (!file) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        const result = e.target?.result as string;

        imageUrl.value = result;
        tempImgToCrop.value = result;
        isOpenModalCrop.value = true;

        setTimeout(() => {
            _cropper.value?.reset();
        }, 0);
    };

    reader.readAsDataURL(file);
};

// convert dataURL to Blob
const dataURLtoBlob = (dataUrl: string) => {
    const arr = dataUrl.split(",");
    const mime = arr[0].match(/:(.*?);/)?.[1] || "image/png";
    const bStr = atob(arr[1]);
    const u8arr = new Uint8Array(bStr.length);

    for (let i = 0; i < bStr.length; i++) {
        u8arr[i] = bStr.charCodeAt(i);
    }

    return new Blob([u8arr], { type: mime });
};

// crop submit
const submitCrop = () => {
    const result = _cropper.value?.getResult();
    if (!result?.canvas) return;

    const dataUrl = result.canvas.toDataURL("image/png");

    imageUrl.value = dataUrl;

    const blob = dataURLtoBlob(dataUrl);
    const file = new File([blob], "cropped.png", { type: "image/png" });

    emits("cropped", file);

    isOpenModalCrop.value = false;
};

// refresh cropper when dialog open
watch(isOpenModalCrop, (val) => {
    if (val) {
        setTimeout(() => {
            _cropper.value?.refresh();
        }, 0);
    }
});

watch(() => props.src_image, (val) => {
    if (!val) {
        imageUrl.value = null;
        return;
    }

    if (typeof val === 'string') {
        imageUrl.value = val;
        return;
    }

    if (val instanceof File) {
        imageUrl.value = URL.createObjectURL(val);
    }
}, { immediate: true });

</script>

<template>
    <div class="w-fit min-w-32">
        <!-- ✅ PRIMEVUE DIALOG -->
        <Dialog
            v-model:visible="isOpenModalCrop"
            modal
            header="Crop Image"
            :style="{ width: '600px' }"
            :breakpoints="{ '960px': '90vw' }"
        >
            <div class="w-full h-[300px] relative bg-gray-700">
                <Cropper
                    :key="numbKey"
                    ref="_cropper"
                    class="w-full h-full"
                    :src="tempImgToCrop"
                    :stencil-props="{
                        aspectRatio: aspectRatio,
                        minAspectRatio: 0.1,
                        maxAspectRatio: 10
                    }"
                    imageClass="w-full h-full"
                    :auto-zoom="true"
                />

                <!-- refresh -->
                <div
                    @click="numbKey++"
                    class="absolute top-2 right-2 px-2 py-1 text-white border border-gray-300 rounded cursor-pointer hover:bg-white/80 hover:text-gray-700"
                >
                    <FontAwesomeIcon icon="fal fa-undo-alt" />
                    {{ trans("Refresh") }}
                </div>
            </div>

            <div class="text-gray-500 italic text-xs mt-2">
                {{ trans("Use mouse scroll to zoom in and zoom out") }}
            </div>

            <div class="w-full mt-6">
                <Button @click="submitCrop" label="Crop" full size="xl" />
            </div>
        </Dialog>

        <!-- PREVIEW -->
        <div class="bg-gray-100 relative overflow-hidden h-40 min-w-32 rounded ring-1 ring-gray-500 shadow">
            <img
                v-if="imageUrl"
                :src="imageUrl"
                class="h-full w-full object-cover rounded"
                alt="preview"
            />

            <div
                v-else
                class="flex items-center justify-center h-full text-xs text-gray-400"
            >
                No image
            </div>

            <!-- overlay -->
            <label
                for="input-avatar"
                class="absolute inset-0 flex items-center justify-center bg-black/50 text-white text-sm opacity-0 hover:opacity-100 cursor-pointer"
            >
                {{ trans("Change") }}

                <input
                    id="input-avatar"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="onPickFile($event.target.files?.[0])"
                />
            </label>
        </div>
    </div>
</template>