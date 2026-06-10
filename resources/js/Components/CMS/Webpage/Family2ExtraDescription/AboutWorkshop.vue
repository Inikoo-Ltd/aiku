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

const containerStyle = computed(() => (getStyles(props.fieldValue?.about?.container?.properties)))

const layoutClasses = computed(() => ({
    wrapper:
        props.screenType === "mobile"
            ? "grid-cols-1"
            : props.screenType === "tablet"
                ? "grid-cols-1"
                : "grid-cols-[53%_47%]",

    left:
        props.screenType === "mobile"
            ? "order-2 py-5"
            : props.screenType === "tablet"
                ? "order-2 py-6"
                : "order-1 flex flex-col py-8 pr-8",

    right:
        props.screenType === "mobile"
            ? "order-1 py-5"
            : props.screenType === "tablet"
                ? "order-1 py-5"
                : "order-2 py-8",

    description:
        props.screenType === "mobile"
            ? "max-w-full text-[13px] leading-[1.8]"
            : props.screenType === "tablet"
                ? "max-w-full text-[14px] leading-[1.8]"
                : "max-w-[700px] 2xl:max-w-[860px] text-[16px] leading-[1.8]",
    galleryWrapper:
        props.screenType === "mobile"
            ? `
            grid
            w-full
            gap-3
            grid-cols-1
        `
            : props.screenType === "tablet"
                ? `
                grid
                w-full
                gap-3
                grid-cols-[1.35fr_1fr]
                grid-rows-[320px_220px]
            `
                : `
                grid
                w-full
                gap-3
                grid-cols-[1.45fr_1fr]
                grid-rows-[380px_220px]
                xl:grid-rows-[420px_240px]
                2xl:grid-rows-[480px_280px]
            `,

    rightColumn:
        props.screenType === "mobile"
            ? `
            grid
            gap-3
            grid-cols-2
            h-[140px]
        `
            : `
            grid
            gap-3
            grid-cols-1
            grid-rows-2
            h-full
        `,

    mainImage:
        props.screenType === "mobile"
            ? "h-[260px]"
            : "h-full",

    bottomImage:
        props.screenType === "mobile"
            ? "h-[220px]"
            : "col-span-2 h-full",
}))

</script>

<template>
    <!-- CONTENT -->
    <div class="grid gap-0 lg:gap-4 items-stretch" :class="layoutClasses.wrapper" :style="containerStyle" >
        <!-- LEFT -->
        <div class="flex flex-col h-full text-center md:text-left" :class="layoutClasses.left">
            <div :class="layoutClasses.description" class="text-[#334155]" v-html="cleanedDescription" />

            <div class="mt-8 md:mt-10">
                <a href="#family-2">
                    <button class="rounded-[8px] border border-[#24384d]
                px-5 md:px-7
                py-[8px]
                text-[12px] md:text-[13px]
                text-[#24384d]" :style="{
                    ...getStyles(fieldValue?.about.button?.container?.properties)
                }">
                        <span v-if="fieldValue?.about?.button?.text">
                            {{ fieldValue?.about?.button?.text }}
                        </span>

                        <span v-else>
                            {{ ctrans('Go back products') }}
                        </span>
                    </button>
                </a>
            </div>
        </div>

        <!-- RIGHT -->

        <div class="flex h-full" :class="layoutClasses.right">
            <div :class="layoutClasses.galleryWrapper">
                <!-- MAIN IMAGE -->
                <div class="overflow-hidden rounded-[8px]" :class="layoutClasses.mainImage">
                    <template v-if="hasImage(displayImages[0])">
                        <Image :src="displayImages[0]?.original" :image-cover="true" class="w-full h-full object-cover"
                            :alt="fieldValue?.family?.name" />
                    </template>

                    <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                        <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div :class="layoutClasses.rightColumn">
                    <!-- IMAGE 2 -->
                    <div class="overflow-hidden rounded-[8px] h-full">
                        <template v-if="hasImage(displayImages[1])">
                            <Image :src="displayImages[1]?.original" :image-cover="true"
                                class="w-full h-full object-cover" :alt="fieldValue?.family?.name" />
                        </template>

                        <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                            <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                        </div>
                    </div>

                    <!-- IMAGE 3 -->
                    <div class="overflow-hidden rounded-[8px] h-full">
                        <template v-if="hasImage(displayImages[2])">
                            <Image :src="displayImages[2]?.original" :image-cover="true"
                                class="w-full h-full object-cover" :alt="fieldValue?.family?.name" />
                        </template>

                        <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                            <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                        </div>
                    </div>
                </div>

                <!-- BOTTOM IMAGE -->
                <div class="relative overflow-hidden rounded-[8px]" :class="layoutClasses.bottomImage">
                    <template v-if="hasImage(displayImages[3])">
                        <Image :src="displayImages[3]?.original" :image-cover="true" class="w-full h-full object-cover"
                            :alt="fieldValue?.family?.name" />
                    </template>

                    <div v-else class="flex h-full w-full items-center justify-center bg-gray-100">
                        <FontAwesomeIcon :icon="faImage" class="text-5xl text-gray-400" />
                    </div>

                    <button @click="openGallery(0)" class="
                    absolute
                    bottom-4
                    right-4
                    rounded-md
                    bg-white/90
                    px-4
                    py-2
                    text-[11px]
                    text-gray-700
                    shadow
                ">
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