<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, onBeforeMount, onMounted, onUnmounted, toRaw } from "vue"
import axios from 'axios'
import Image from '@/Components/Image.vue'
import { notify } from '@kyvg/vue3-notification'
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ImageData } from '@/types/Image'
import { Images } from "@/types/Images"
import { router } from '@inertiajs/vue3'
import { Links, Meta } from "@/types/Table"
import { debounce } from "lodash-es"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheckCircle } from '@fas'
import { faSearch } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import PureInputWithAddOn from "@/Components/Pure/PureInputWithAddOn.vue"
library.add(faCheckCircle, faSearch)


const props = defineProps<{
    imagesUploadedRoutes?: routeType
    attachImageRoute: routeType
    closePopup?: Function
    maxSelected?: number
}>()

const selectedIdImages = ref<number[]>([])

const emits = defineEmits<{
    (e: 'selectImage', value: {}): void
    (e: 'optionsList', value: {}[]): void
    (e: 'submitSelectedImages', value: ImageData[]): void
}>()


// Method: select and unselect image
const toggleImageSelection = (imageId: number) => {
    const index = selectedIdImages.value.indexOf(imageId);

    if (index > -1) {
        // If it exists, remove it
        selectedIdImages.value.splice(index, 1);
    } else {
        // If it doesn't exist
        if (props.maxSelected === 1) {
            // If maxSelected is 1, just select and unselect without warning
            selectedIdImages.value = [imageId];
        } else if (!props.maxSelected || selectedIdImages.value.length < props.maxSelected) {
            // Add it if maxSelected is not defined or not reached
            selectedIdImages.value.push(imageId);
        } else {
            notify({
                title: trans('Selection limit reached'),
                text: trans(`You can only select up to ${props.maxSelected} images.`),
                type: 'warning',
            });
        }
    }
};

// Method: submit selected stock images
const isLoadingSubmit = ref<boolean>(false)
const submitSelectedImages = () => {
    if (props.attachImageRoute?.name) {
        router.post(
            route(props.attachImageRoute.name, props.attachImageRoute.parameters),
            {
                images: selectedIdImages.value
            },
            {
                onStart: () => isLoadingSubmit.value = true,
                onFinish: (aaa) => {
                    isLoadingSubmit.value = false
                },
                onSuccess: (zzz) => {
                    selectedIdImages.value = [];
                    if (props.closePopup) {
                        props.closePopup();
                    }
                },
                onError: (err) => {
                    notify({
                        title: trans('Something went wrong.'),
                        text: err?.message || '',
                        type: 'error',
                    })
                }
            }
        )
    }
    
    const selectedImages = toRaw(optionsList.value.filter((option) => selectedIdImages.value.includes(option.id)))
    // console.log('oplistzzz', selectedImages)
    emits('submitSelectedImages',  selectedImages)
}



const isLoading = ref<string | boolean>(false)

const getUrlFetch = (additionalParams: {}) => {
    return route(
        props.imagesUploadedRoutes.name,
        {
            ...props.imagesUploadedRoutes.parameters,
            ...additionalParams
        }
    )
}

const optionsList = ref<any[]>([])
const optionsMeta = ref<Meta | null>(null)
const optionsLinks = ref<Links | null>(null)
const fetchProductList = async (url?: string) => {
    isLoading.value = 'fetchProduct'

    const urlToFetch = url || route(props.imagesUploadedRoutes.name, props.imagesUploadedRoutes.parameters)

    try {
        const xxx = await axios.get(urlToFetch)
        
        optionsList.value = [...optionsList.value, ...xxx?.data?.data]
        optionsMeta.value = xxx?.data.meta || null
        optionsLinks.value = xxx?.data.links || null

        // console.log('fetch', optionsList.value)

        emits('optionsList', optionsList.value)
    } catch (error) {
        // console.log(error)
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch product list'),
            type: 'error',
        })
    }
    isLoading.value = false
}
    
const onSearchQuery = debounce(async (query: string) => {
    optionsList.value = []
    fetchProductList(getUrlFetch({'filter[global]': query}))
}, 500)


// Method: fetching next page
const onFetchNext = () => {
    const _imagesView = document.querySelector('#imagesView')
    // console.log(_imagesView?.scrollTop, _imagesView?.clientHeight, _imagesView?.scrollHeight)

    const bottomReached = (_imagesView?.scrollTop || 0) + (_imagesView?.clientHeight || 0) >= (_imagesView?.scrollHeight || 10) - 10
    if (bottomReached && optionsLinks.value?.next && isLoading.value != 'fetchProduct') {
        // console.log(_imagesView?.scrollTop, _imagesView?.clientHeight, _imagesView?.scrollHeight)
        fetchProductList(optionsLinks.value.next)
    }
}

onMounted(async () => {
    fetchProductList()
    const _imagesView = document.querySelector('#imagesView')
    if (_imagesView) {
        _imagesView.addEventListener('scroll', () => onFetchNext())
    }
})

onUnmounted(() => {
    const _imagesView = document.querySelector('#imagesView')
    if (_imagesView) {
        _imagesView.removeEventListener('scroll', () => onFetchNext())
    }
})
</script>

<template>
    <div class="h-full relative isolate pr-4 flex flex-col">
        <!-- <template v-if="!isLoading"> -->
            <div class="sticky top-0 pb-2 z-10 bg-white ">
                <div class="pb-2 flex justify-between border-b border-gray-300 ">
                    <div class="text-2xl font-semibold tabular-nums">
                        <!-- {{ trans('Select images') }} ({{ selectedImages.length }}/{{ optionsList.length }}) -->
                        <PureInputWithAddOn
                            @update:model-value="(val) => onSearchQuery(val)"
                            :leftAddOn="{ icon: 'fal fa-search' }"
                        />
                    </div>

                    <div class="flex items-end gap-x-2">
                        <div @click="() => selectedIdImages.length ? selectedIdImages = [] : false"
                            class=""
                            :class="selectedIdImages.length ? 'underline cursor-pointer' : 'text-gray-400'"
                        >
                            {{ trans('Unselect all') }}
                        </div>
                        <Button
                            :label="`Select image ${selectedIdImages.length}/${maxSelected || optionsList.length}`"
                            @click="() => submitSelectedImages()"
                            :loading="isLoadingSubmit"
                            :disabled="!selectedIdImages.length"
                        />
                    </div>
                </div>
            </div>

            <div id="imagesView" class="overflow-y-auto h-full select-none">
                <template v-if="optionsList.length">
                    <div class="flex flex-wrap justify-around gap-2">
                        <div
                            v-for="option in optionsList"
                            class="relative min-h-10 h-20 max-h-24 min-w-20 w-auto border rounded cursor-pointer transition-all"
                            @click="() => toggleImageSelection(option.id)"
                            :class="selectedIdImages.includes(option.id) ? 'border-blue-400 scale-[97%]' : 'border-gray-300'"
                        >
                            <Image :src="option.thumbnail" />
                            <div v-if="selectedIdImages.includes(option.id)" class="absolute inset-0 bg-blue-500/40"
                            />
                            <FontAwesomeIcon v-if="selectedIdImages.includes(option.id)" icon='fas fa-check-circle' class='absolute top-1 right-1 text-green-500' fixed-width aria-hidden='true' />

                            <div class="flex items-end absolute h-1/2 bottom-0 bg-gradient-to-t from-black/60 via-black/30 to-transparent w-full truncate text-xs pl-1 pb-1 text-white">
                                {{ option.name }}
                            </div>
                        </div>
                    </div>
            
                    <div v-if="optionsLinks?.next" class="mt-8 flex justify-center">
                        <Button @click="onFetchNext" :label="trans('Load more')" :loading="!!isLoading" type="tertiary" />
                    </div>
                </template>

                <div v-else-if="!isLoading" class="flex justify-center col-span-4">
                    <EmptyState :data="{ title : trans('You dont have images'), description : ''}"/>
                </div>

                <div v-else class="flex gap-x-2">
                    <div class="h-16 w-full rounded skeleton"></div>
                    <div class="h-16 w-full rounded skeleton"></div>
                    <div class="h-16 w-full rounded skeleton"></div>
                    <div class="h-16 w-full rounded skeleton"></div>
                </div>
            </div>
        <!-- </template> -->

        <!-- <div v-else class="flex justify-center items-center">
            <LoadingIcon class="text-4xl" />
        </div> -->
        
    </div>
</template>
