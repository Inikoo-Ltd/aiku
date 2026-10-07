<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, onMounted, toRaw } from "vue"
import axios from 'axios'
import Image from "@common/Components/Image.vue"
import { notify } from '@kyvg/vue3-notification'
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { routeType } from "@/types/route"
import { ctrans } from "@/Composables/useTrans"
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
    fill?: boolean
}>()

const tilesGridClass = 'grid gap-3 grid-cols-[repeat(auto-fill,minmax(6rem,1fr))]'

const getRetinaThumbnail = (thumbnail?: Record<string, any> | null) => ({
    avif: thumbnail?.avif_2x,
    webp: thumbnail?.webp_2x,
    original: thumbnail?.original_2x,
})

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
                title: ctrans('Selection limit reached'),
                text: ctrans(`You can only select up to :maxSelected images.`, { maxSelected: String(props.maxSelected ?? 0) }),
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
                        title: ctrans('Something went wrong.'),
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
            title: ctrans('Something went wrong.'),
            text: ctrans('Failed to fetch product list'),
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
    if (optionsLinks.value?.next && isLoading.value != 'fetchProduct') {
        fetchProductList(optionsLinks.value.next)
    }
}

onMounted(() => {
    fetchProductList()
})
</script>

<template>
    <div class="relative isolate flex h-full flex-col" :class="fill ? 'min-h-0' : 'pr-4'">
        <!-- <template v-if="!isLoading"> -->
            <div class="sticky top-0 z-10 shrink-0 bg-white pb-2" :class="fill ? 'px-0.5 pt-1' : ''">
                <div class="flex flex-wrap items-center justify-between gap-2 pb-2" :class="fill ? '' : 'border-b border-gray-300'">
                    <div class="w-full min-w-[12rem] max-w-xs flex-1">
                        <PureInputWithAddOn
                            @update:model-value="(val) => onSearchQuery(val)"
                            :leftAddOn="{ icon: 'fal fa-search' }"
                            :placeholder="ctrans('Search images')"
                        />
                    </div>

                    <div class="flex items-center gap-x-3">
                        <button
                            type="button"
                            @click="selectedIdImages = []"
                            :disabled="!selectedIdImages.length"
                            class="text-sm underline underline-offset-2 text-gray-500 hover:text-gray-700 disabled:no-underline disabled:text-gray-300 disabled:cursor-not-allowed"
                        >
                            {{ ctrans('Unselect all') }}
                        </button>
                        <Button
                            :label="`${ctrans('Select image')} ${selectedIdImages.length}${maxSelected ? '/' + maxSelected : ''}`"
                            @click="() => submitSelectedImages()"
                            :loading="isLoadingSubmit"
                            :disabled="!selectedIdImages.length"
                        />
                    </div>
                </div>
            </div>

            <div id="imagesView" class="select-none overflow-y-auto overscroll-contain" :class="fill ? 'min-h-0 flex-1 rounded-md border border-gray-200 bg-gray-50 p-3' : 'h-full max-h-[60vh]'">
                <template v-if="optionsList.length">
                    <div v-if="fill" class="columns-[9.5rem] gap-3">
                        <button
                            v-for="option in optionsList"
                            :key="option.id"
                            type="button"
                            class="group relative mb-3 block w-full break-inside-avoid overflow-hidden rounded-md border bg-white text-left transition-all focus:outline-none"
                            :class="selectedIdImages.includes(option.id) ? 'border-blue-400 ring-2 ring-blue-400' : 'border-gray-200 hover:border-gray-400 hover:shadow-md'"
                            :title="option.name"
                            :aria-pressed="selectedIdImages.includes(option.id)"
                            @click="() => toggleImageSelection(option.id)"
                            @dblclick="() => { if (maxSelected === 1) { selectedIdImages = [option.id]; submitSelectedImages() } }"
                        >
                            <div class="relative bg-[repeating-conic-gradient(#f3f4f6_0%_25%,#ffffff_0%_50%)] bg-[length:16px_16px]">
                                <Image :src="option.preview ?? option.thumbnail" :alt="option.alt || option.name || option.slug" class="block w-full" :style="{ height: 'auto', width: '100%', display: 'block' }" />
                                <div v-if="selectedIdImages.includes(option.id)" class="absolute inset-0 bg-blue-500/20" />
                                <FontAwesomeIcon v-if="selectedIdImages.includes(option.id)" icon="fas fa-check-circle" class="absolute right-1.5 top-1.5 rounded-full bg-white text-lg text-blue-500" fixed-width aria-hidden="true" />
                            </div>
                            <div class="px-2 py-1.5">
                                <div class="line-clamp-2 break-words text-[11px] leading-snug text-gray-700">{{ option.name }}</div>
                                <div v-if="option.size" class="mt-0.5 text-[10px] text-gray-400">{{ option.size }}</div>
                            </div>
                        </button>
                    </div>

                    <div v-else :class="tilesGridClass">
                        <div
                            v-for="option in optionsList"
                            class="group relative aspect-square cursor-pointer overflow-hidden rounded border bg-white transition-all"
                            @click="() => toggleImageSelection(option.id)"
                            @dblclick="() => { if (maxSelected === 1) { selectedIdImages = [option.id]; submitSelectedImages() } }"
                            :title="option.name"
                            :class="selectedIdImages.includes(option.id) ? 'scale-[97%] border-blue-400 ring-2 ring-blue-400' : 'border-gray-300 hover:border-gray-400 hover:shadow-md'"
                        >
                            <Image :src="option.thumbnail" :srcset="getRetinaThumbnail(option.thumbnail)" :alt="option.alt || option.slug" :imageCover="true" />
                            <div v-if="selectedIdImages.includes(option.id)" class="absolute inset-0 bg-blue-500/40"
                            />
                            <FontAwesomeIcon v-if="selectedIdImages.includes(option.id)" icon='fas fa-check-circle' class='absolute top-1 right-1 text-green-500' fixed-width aria-hidden='true' />

                            <div class="flex items-end absolute h-1/2 bottom-0 bg-gradient-to-t from-black/60 via-black/30 to-transparent w-full truncate text-xs pl-1 pb-1 text-white">
                                {{ option.name }}
                            </div>
                        </div>
                    </div>
            
                    <div v-if="optionsLinks?.next" class="mt-8 flex justify-center">
                        <Button @click="onFetchNext" :label="ctrans('Load more')" :loading="!!isLoading" type="tertiary" />
                    </div>
                </template>

                <div v-else-if="!isLoading" class="flex justify-center col-span-4">
                    <EmptyState :data="{ title : ctrans('You dont have images'), description : ''}"/>
                </div>

                <div v-else-if="fill" class="columns-[9.5rem] gap-3">
                    <div v-for="index in 18" :key="index" class="skeleton mb-3 break-inside-avoid rounded-md border border-gray-200" :style="{ height: `${[120, 180, 150, 210, 135, 165][index % 6]}px` }" />
                </div>

                <div v-else :class="tilesGridClass">
                    <div v-for="index in 18" :key="index" class="aspect-square rounded border border-gray-200 skeleton" />
                </div>
            </div>
        <!-- </template> -->

        <!-- <div v-else class="flex justify-center items-center">
            <LoadingIcon class="text-4xl" />
        </div> -->
        
    </div>
</template>
