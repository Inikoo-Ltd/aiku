<script setup lang="ts">
import { computed, ref } from "vue"
import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import Dialog from 'primevue/dialog'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@far"

const props = defineProps<{
    fieldValue: any
    screenType: "mobile" | "tablet" | "desktop"
}>()

const family = computed(() => props.fieldValue?.family ?? {})
const displayImages = computed(() => {
    const source = family.value.extra_description_image
    const values = Array.isArray(source)
        ? source
        : Object.values(source ?? {})

    return [...values.slice(0, 4), null, null, null].slice(0, 4)
})

const cleanedDescription = computed(() => {
    const html = String(family.value.description_extra ?? "")
    return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const showModal = ref(false)
const selectedIndex = ref(0)

const galleryImages = computed(() => {
    const source = family.value.extra_description_image

    if (Array.isArray(source)) {
        return source.filter(Boolean)
    }

    return Object.values(source ?? {}).filter(Boolean)
})

const openGallery = (index = 0) => {
    selectedIndex.value = index
    showModal.value = true
}

const closeGallery = () => {
    showModal.value = false
}

const onPrevNavigation = () => {
    if (selectedIndex.value === 0) {
        selectedIndex.value = galleryImages.value.length - 1
        return
    }

    selectedIndex.value--
}

const onRightNavigation = () => {
    if (selectedIndex.value === galleryImages.value.length - 1) {
        selectedIndex.value = 0
        return
    }

    selectedIndex.value++
}


const hasImage = (image: any) => {
    return image?.original && String(image?.original).trim() !== ""
}

console.log("Family Extra Description 2 Props:", props)
</script>

<template>
    <!-- CONTENT -->
    <div class="grid grid-cols-1 lg:grid-cols-[48%_52%] 2xl:grid-cols-[46%_54%]">
        <!-- LEFT -->
        <div class="order-2 lg:order-1 flex flex-col py-5 md:py-6 lg:py-8 lg:pr-8 2xl:pr-12">
            <div class="
             max-w-full lg:max-w-[500px] 2xl:max-w-[700px]
             text-[13px]
             md:text-[14px]
             2xl:text-[16px]
             leading-[1.8]
             text-[#334155]" v-html="cleanedDescription">
            </div>

            <div class="mt-8 md:mt-10 lg:mt-auto">
                <a href="#family-2">
                    <button class="rounded-[8px] border border-[#24384d]
               px-5 md:px-7
               py-[8px]
               text-[12px] md:text-[13px]
               text-[#24384d]" :style="{
                ...getStyles(fieldValue?.button?.container?.properties)
            }">
                        <span v-if="fieldValue?.button?.text">{{ fieldValue?.button?.text }}</span>
                        <span v-else>{{ ctrans('Go back products') }}</span>
                    </button>
                </a>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="order-1 lg:order-2 py-5 lg:p-[14px]">
            <div class="grid gap-[10px]
           grid-cols-1
           md:grid-cols-[1fr_180px]
           lg:grid-cols-[1fr_148px]
           2xl:grid-cols-[1fr_200px]">
                <!-- Main Image -->
                <div class="overflow-hidden rounded-[8px]
             h-[250px]
             md:h-[320px]
             lg:h-[238px]
             2xl:h-[320px]">
                    <template v-if="hasImage(displayImages[0])">
                        <Image :src="displayImages[0]?.original" :image-cover="true" class="w-full h-full object-cover"
                            :alt="fieldValue?.family?.name" />
                    </template>

                    <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                        <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                    </div>
                </div>

                <!-- Side Images -->
                <div class="grid grid-cols-2 md:grid-cols-1 gap-[10px]">
                    <div class="overflow-hidden rounded-[8px]
               h-[120px]
               md:h-[155px]
               lg:h-[114px]
               2xl:h-[155px]">
                        <template v-if="hasImage(displayImages[1])">
                            <Image :src="displayImages[1]?.original" :image-cover="true" class="w-full h-full object-cover"
                                :alt="fieldValue?.family?.name" />
                        </template>

                        <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                            <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[8px]
               h-[120px]
               md:h-[155px]
               lg:h-[114px]
               2xl:h-[155px]">
                        <template v-if="hasImage(displayImages[2])">
                            <Image :src="displayImages[2]?.original" :image-cover="true" class="w-full h-full object-cover"
                                :alt="fieldValue?.family?.name" />
                        </template>

                        <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                            <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                        </div>
                    </div>
                </div>

                <!-- Bottom Image -->
                <div class="relative md:col-span-2 overflow-hidden rounded-[8px]
             h-[240px]
             md:h-[320px]
             lg:h-[255px]
             2xl:h-[350px]">
                    <template v-if="hasImage(displayImages[3])">
                        <Image :src="displayImages[3]?.original" :image-cover="true" class="w-full h-full object-cover"
                            :alt="fieldValue?.family?.name" />
                    </template>

                    <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                        <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                    </div>

                    <button @click="openGallery(0)" class="absolute bottom-4 right-4
               rounded-md bg-white/90
               px-4 py-2
               text-[11px]
               text-gray-700
               shadow">
                        {{ ctrans('Image Gallery') }}
                    </button>
                </div>
            </div>
        </div>
    </div>


    <Dialog v-model:visible="showModal" modal dismissableMask :closable="false" class="w-full max-w-6xl" :pt="{
        root: '!bg-transparent !shadow-none !border-0',
        content: '!bg-transparent p-0'
    }">
        <div class="relative flex items-center justify-center">
            <!-- Close -->
            <button class="absolute right-4 top-4 z-50 text-white text-3xl" @click="closeGallery">
                ✕
            </button>

            <!-- Previous -->
            <button class="absolute left-4 top-1/2 z-50 -translate-y-1/2 text-white text-5xl" @click="onPrevNavigation">
                ‹
            </button>

            <!-- Fixed Preview Area -->
            <div class="flex items-center justify-center
           w-full
           h-[300px]
           md:h-[500px]
           lg:h-[500px]">
                <Image :src="galleryImages[selectedIndex].original" :alt="`Image ${selectedIndex + 1}`"
                    class="w-full h-full object-contain flex justify-center" :image-cover="false" />
            </div>

            <!-- Next -->
            <button class="absolute right-4 top-1/2 z-50 -translate-y-1/2 text-white text-5xl"
                @click="onRightNavigation">
                ›
            </button>
        </div>
        <!-- Thumbnail -->
        <div class="mt-4 flex justify-center gap-2 overflow-x-auto pb-2">
            <button v-for="(image, index) in galleryImages" :key="index" @click="selectedIndex = index"
                class="h-20 w-20 min-h-20 min-w-20 overflow-hidden rounded border-2 flex-shrink-0" :class="selectedIndex === index
                    ? 'border-white'
                    : 'border-transparent'
                    ">
                <Image :src="image.original" class="h-full w-full object-cover" :image-cover="true" />
            </button>
        </div>
    </Dialog>
</template>

<style scoped>
:deep(p) {
    margin-bottom: 18px;
}

:deep(p:last-child) {
    margin-bottom: 0;
}

:deep(h2),
:deep(h3),
:deep(h4),
:deep(h5),
:deep(h6) {
    margin-top: 18px;
    margin-bottom: 12px;
    font-weight: 500;
    color: #22374a;
}

:deep(ul),
:deep(ol) {
    margin-bottom: 18px;
    padding-left: 20px;
}

:deep(li) {
    margin-bottom: 6px;
}

:deep(img) {
    max-width: 100%;
}
</style>