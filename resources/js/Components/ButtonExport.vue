<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Popover } from "primevue"
import { faSyncAlt } from "@fas";
import { ref } from "vue";
import {
    faBracketsCurly, faPawClaws,
    faFileExcel,
    faImage,
    faArrowLeft,
    faArrowRight,
    faUpload,
    faBox,
    faEllipsisV,
    faDownload
} from "@fal";

library.add(faFileExcel, faBracketsCurly, faSyncAlt, faPawClaws, faImage, faSyncAlt, faBox, faArrowLeft, faArrowRight, faUpload);


const props = defineProps<{
    download_route: any
}>();

const downloadUrl = (type: string) => {
    if (props.download_route?.[type]?.name) {
        return route(props.download_route[type].name, props.download_route[type].parameters);
    } else {
        return ''
    }
};

const _popover = ref()
</script>

<template>
    <div class="rounded-md ">
        <a :href="downloadUrl('csv') as string" target="_blank" rel="noopener">
            <Button :icon="faDownload" label="CSV" type="tertiary" class="rounded-r-none" />
        </a>

        <a :href="downloadUrl('images') as string" target="_blank" rel="noopener">
            <Button :icon="faImage" label="Images" type="tertiary" class="border-l-0 border-r-0 rounded-none" />
        </a>

        <!-- Section: Download button -->
        <Button @click="(e) => _popover?.toggle(e)" v-tooltip="trans('Open another options')" :icon="faEllipsisV"
            xloading="!!isLoadingSpecificChannel.length" class="!px-2 border-l-0 rounded-l-none h-full" type="tertiary"
            key="" />

        <Popover ref="_popover">
            <div class="w-64 relative">
                <div class="text-sm mb-2">
                    {{ trans("Select another download file type") }}:
                </div>

                <div class="flex flex-col gap-y-2">
                    <a :href="downloadUrl('xlsx') as string" target="_blank" rel="noopener">
                        <Button :icon="faFileExcel" label="Excel" full :style="'tertiary'" />
                    </a>
                    <a :href="downloadUrl('json') as string" target="_blank" rel="noopener">
                        <Button :icon="faBracketsCurly" label="JSON" full :style="'tertiary'" />
                    </a>
                    <a :href="downloadUrl('images') as string" target="_blank" rel="noopener">
                        <Button :icon="faImage" :label="trans('Images')" full :style="'tertiary'" />
                    </a>
                </div>

            </div>
        </Popover>
    </div>
</template>
