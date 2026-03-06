<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Jul 2023 22:01:23 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import "swiper/css"
import "swiper/css/navigation"
import axios from 'axios'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fad'
import { faExclamation } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faExclamation, faSpinnerThird)

import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import CropComponents from "./CropComponents.vue"
import { routeType } from '@/types/route'


const props = withDefaults(defineProps<{
    data: File[] | File;
    imagesUploadRoute: string
    response?: any
    ratio?: Array<any>,
}>(), {
    ratio: [4 / 1] 
})

const emits = defineEmits<{
    (e: 'onFinishCropped', image: any): void
}>()

const parseRatio = (value: number | string): number | null => {
    if (typeof value === "number") return value

    if (typeof value === "string") {
        if (value.includes("/")) {
            const [w, h] = value.split("/").map(Number)
            if (!isNaN(w) && !isNaN(h) && h !== 0) {
                return w / h
            }
        }

        const num = Number(value)
        if (!isNaN(num)) return num
    }

    return null
}

const aspectRatios = computed<number[]>(() => {
    const ratio = props.ratio
    const list = Array.isArray(ratio) ? ratio : [ratio]

    return list
        .map(parseRatio)
        .filter((r): r is number => r !== null)
})
const selectedRatio = ref(aspectRatios.value[0])

const setData2 = () => {
    const data = []
    for (const set of props.data) {
        data.push({
            originalFile: set,
        })
    }
    return data
}

const setData = ref(setData2())

const generateThumbnail = (file: { originalFile: File, imagePosition: any }) => {
    if (
        file.originalFile &&
        file.originalFile instanceof File &&
        !file.imagePosition
    ) {
        let fileSrc = URL.createObjectURL(file.originalFile);
        setTimeout(() => {
            URL.revokeObjectURL(fileSrc);
        }, 200)
        return fileSrc
    } else if (file.imagePosition) {
        return file.imagePosition.canvas.toDataURL()
    } else {
        return file.originalFile
    }
};

const form = ref(new FormData())
const catchError = ref()
const loadingState = ref(false)

const addComponent = async () => {
    loadingState.value = true
    const SendData = []

    const processItem = async (item) => {
        return new Promise((resolve, reject) => {
            if (item.imagePosition) {
                item.imagePosition.canvas.toBlob((blob) => {
                    // SendData.push(blob)
                    form.value.append("blob", blob, item.originalFile.name)
                    resolve()
                })
            } else if (item.originalFile.type == 'image/gif') {
                form.value.append("gif", item.originalFile)
                resolve()
            } else {
                resolve()
            }
        });
    };

    await Promise.all(setData.value.map(processItem))

    for (const [key, value] of form.value.entries()) {
        SendData.push(value)
        // console.log((value.size / (1024 * 1024)).toFixed(2))
    }

    try {
        const response = await axios.post(
            props.imagesUploadRoute,
            { images: SendData },
            {
                headers: { "Content-Type": "multipart/form-data" },
            }
        );
        form.value = new FormData()
        props?.response(response.data)
        emits('onFinishCropped', response.data)
        loadingState.value = false
    } catch (error) {
        console.log(error)
        form.value = new FormData()
        catchError.value = error.response.data.message || "Failed to Upload"
        // props.response(error.response)
        loadingState.value = false
    }
}

const current = ref(0)

const generateGif = (file: File) => {
    let fileSrc = URL.createObjectURL(file)
    setTimeout(() => {
        URL.revokeObjectURL(fileSrc)
    }, 1000)
    return fileSrc
}

const knownRatios: Record<number, string> = {
    1: "1:1",
    [4 / 3]: "4:3",
    [3 / 4]: "3:4",
    [16 / 9]: "16:9",
    [9 / 16]: "9:16",
    [5 / 4]: "5:4",
    [4 / 5]: "4:5",
}

const formatRatioLabel = (ratio: number | null) => {
    if (ratio === null) return "Custom"
    const closest = Object.keys(knownRatios)
        .map(Number)
        .reduce((prev, curr) =>
            Math.abs(curr - ratio) < Math.abs(prev - ratio) ? curr : prev
        )
    if (Math.abs(closest - ratio) < 0.01) {
        return knownRatios[closest]
    }
    return `${Math.round(ratio * 100) / 100}:1`
}

watch(aspectRatios, (newRatios) => {
    if (!newRatios.includes(selectedRatio.value)) {
        selectedRatio.value = newRatios[0]
    }
})


</script>

<template>
    {{ ratio }}
    <!-- Preview cropped image -->
    <div class="mb-6 relative w-full flex justify-center">
        <div class="flex items-center border-2 border-gray-500 shadow-md" :class="[
            ratio ? `aspect-[4/1]` : 'aspect-[2/1] md:aspect-[3/1] lg:aspect-[4/1]'
        ]">
            <img :src="generateThumbnail(setData[current])" alt="" class="w-full h-full object-fit" />
        </div>
    </div>

    <div class="mb-6 space-y-3">
        <div v-if="aspectRatios.length > 1" class="flex gap-2 justify-center mb-2">
            <Button v-for="ratio in aspectRatios" :key="ratio + selectedRatio" :label="formatRatioLabel(ratio)"
                :type="selectedRatio === ratio ? 'primary' : 'tertiary'" size="xs" @click="selectedRatio = ratio" />
        </div>

        <div class="max-w-full py-5 px-6 h-96 overflow-y-auto border border-solid border-gray-300 rounded-lg">
            <ul role="list"
                class="mx-auto grid max-w-full grid-cols-1 gap-x-8 gap-y-16 sm:grid-cols-2 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                <li v-for="(item, index) in setData" :key="index">
                    <div @click="current = index"
                        :class="['p-2.5 border border-solid rounded-lg cursor-pointer ', setData[current] == item ? 'border-gray-400 bg-gray-200' : 'border-gray-300']">
                        <CropComponents 
                            v-if="item.originalFile.type !== 'image/gif'" 
                            :data="item" 
                            :ratio="selectedRatio" 
                        />
                        <div v-else> <img :src="generateGif(item.originalFile)" :alt="item.originalFile.name"></div>
                        <h3 class="flex justify-start align-middle leading-5 tracking-tight truncate w-full"
                            :class="[setData[current] == item ? 'text-amber-500 font-semibold' : 'text-gray-500']">
                            {{ item.originalFile.name }}
                        </h3>
                    </div>
                </li>
            </ul>
        </div>
        <div v-if="catchError" class="text-red-500">
            <FontAwesomeIcon icon='fas fa-exclamation' class='' aria-hidden='true' />
            {{ catchError }}
        </div>
    </div>
    <div class="">
        <Button :style="`primary`" icon="fas fa-upload" class="relative" :disabled="loadingState" size="xs"
            @click="addComponent" label="Save Image" :loading="loadingState" />
    </div>
</template>

<style lang="scss" scoped></style>
