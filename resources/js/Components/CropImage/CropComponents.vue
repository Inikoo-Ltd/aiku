<script setup>
import { ref, h, defineComponent } from "vue";
import { set, get } from 'lodash-es';
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css';
import 'vue-advanced-cropper/dist/theme.compact.css';

const props = defineProps(["data","ratio"]);
const _cropper = ref()

const cropOnChange = ({ coordinates, image, visibleArea, canvas }) => {
    set(props, ['data', 'imagePosition'], {coordinates, image, visibleArea, canvas})
}

const onReady = () => {
    _cropper.value.setCoordinates({
          left: get(props.data,['imagePosition','coordinates','left'],0), // Set the left position to 0
          top: get(props.data,['imagePosition','coordinates','top'],0), // Set the top position to 0
        });
}


const generateThumbnail = (fileOrUrl) => {
        if (fileOrUrl.originalFile instanceof File) {
            let fileSrc = URL.createObjectURL(fileOrUrl.originalFile);
            setTimeout(() => {
                URL.revokeObjectURL(fileSrc);
            }, 1000);
            return fileSrc;
        } else if (fileOrUrl.originalFile  === "string") {
            return fileOrUrl.originalFile ;
        }
};

</script>

<template>
    <div class="block w-full">
        <div class="w-full overflow-hidden relative">
            <Cropper ref="_cropper" class="w-[400px] md:w-[440px] h-[200px] rounded-2xl object-cover" :src="generateThumbnail(props.data)" :stencil-props="{
                aspectRatio:  props.ratio.w / props.ratio.h,
                movable: true,
                resizable: true,
            }" :auto-zoom="true"  @ready="onReady" @change="cropOnChange" >
            </Cropper>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.cropper {
    height: 200px;
    width: 400px;
    @apply md:w-[400px] 
}
</style>