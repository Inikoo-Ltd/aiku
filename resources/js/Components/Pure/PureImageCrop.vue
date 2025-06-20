<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { ref, watch } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExclamationCircle, faCheckCircle } from "@fas";
import { faUndoAlt } from "@fal";
import { faSpinnerThird } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import Image from "@/Components/Image.vue";
import { Cropper } from "vue-advanced-cropper";
import "vue-advanced-cropper/dist/style.css";
import Modal from "@/Components/Utils/Modal.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { Image as ImageProxy } from "@/types/Image";

library.add(faSpinnerThird, faExclamationCircle, faCheckCircle, faSpinnerThird, faUndoAlt);


const props = defineProps<{
    src_image?: ImageProxy
    aspectRatio: number
}>();

const emits = defineEmits<{
    (e: "cropped", value: File): void
}>();


const numbKey = ref(0);
const tempImgToCrop = ref<string | null>(null);
const imgAfterCrop = ref<Blob | null | ImageProxy>(props.src_image ?? null);
const onPickFile = async (file: File) => {
    if (!file) return;
    _cropper.value?.reset();
    isOpenModalCrop.value = true;
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (e) => {
        tempImgToCrop.value = e.target?.result as string;
    };
};

// Helper
const dataURLtoBlob = (dataUrl) => {
    const arr = dataUrl.split(",");
    const mime = arr[0].match(/:(.*?);/)[1];
    const bStr = atob(arr[1]);
    let n = bStr.length;
    const u8arr = new Uint8Array(n);

    while (n--) {
        u8arr[n] = bStr.charCodeAt(n);
    }

    return new Blob([u8arr], { type: mime });
};

const isOpenModalCrop = ref(false);
const _cropper = ref<InstanceType<typeof Cropper> | null>(null);
const submitCrop = () => {
    const { coordinates, canvas } = _cropper.value?.getResult();
    if (!canvas) return;

    const imageDataURL = canvas.toDataURL();
    imgAfterCrop.value = {
        original: imageDataURL
    };
    const imageBlob = dataURLtoBlob(imageDataURL);
    const imageFile = new File([imageBlob], "img.png", { type: "image/png" });

    emits("cropped", imageFile);

    isOpenModalCrop.value = false;
};


watch(isOpenModalCrop, (value) => {
    _cropper.value?.refresh();
});
</script>

<template>
    <div class="w-fit min-w-32">
        <Modal :isOpen="isOpenModalCrop" @close="isOpenModalCrop = false" width="max-w-xl w-full" :zIndex="999">
            <div class="w-full h-[300px] relative bg-gray-700">
                <Cropper
                    :key="numbKey"
                    ref="_cropper"
                    class="w-full h-full"
                    :src="tempImgToCrop"
                    :stencil-props="{
                        aspectRatio: 1,
                        minAspectRatio: 0.1,
                        maxAspectRatio: 10
                    }"
                    imageClass="w-full h-full"
                    :auto-zoom="true"
                />
                <div @click="() => numbKey++" class="select-none px-2 py-1 cursor-pointer absolute top-2 text-white right-2 border border-gray-300 hover:bg-white/80 hover:text-gray-700 rounded">
                    <FontAwesomeIcon icon="fal fa-undo-alt" class="" fixed-width aria-hidden="true" />
                    {{ trans("Refresh") }}
                </div>
            </div>

            <div class="text-gray-500 italic text-xs mt-1">
                <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width aria-hidden="true" />
                {{ trans("Use mouse scroll to zoom in and zoom out") }}
            </div>

            <div class="w-full mt-8">
                <Button @click="submitCrop" label="Crop" full size="xl" />
            </div>
        </Modal>

        <!-- Avatar Button: Large view -->
        <div class="bg-gray-100 relative overflow-hidden h-40 min-w-32 w-auto aspect-ratio rounded lg:inline-block ring-1 ring-gray-500 shadow">
            <Image class="h-full rounded" :src="imgAfterCrop" alt="" />
            <label id="input-avatar-large-mask" for="input-avatar-large"
                   class="absolute inset-0 flex h-full w-full items-center justify-center bg-black bg-opacity-50 text-sm font-medium text-white opacity-0 hover:opacity-100">
                <span>{{ trans("Change") }}</span>
                <input type="file" @input="onPickFile($event.target.files[0])" id="input-avatar-large" name="input-avatar-large" accept="image/*"
                       class="absolute inset-0 h-full w-full cursor-pointer rounded-md border-gray-300 opacity-0" />
            </label>
        </div>


    </div>
</template>


