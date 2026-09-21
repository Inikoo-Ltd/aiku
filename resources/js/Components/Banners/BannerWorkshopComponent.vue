<script setup lang="ts">
import { ref, computed, provide, watch, nextTick, toRef } from "vue"
import { cloneDeep, get, set } from "lodash-es"
import SlidesWorkshop from "@/Components/Banners/SlidesWorkshop.vue"
import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue"
import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue"
import SlidesWorkshopAddMode from "@/Components/Banners/SlidesWorkshopAddMode.vue"
import ScreenView from "@/Components/ScreenView.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import BannerCanvasOverlay from "@/Components/Banners/Canvas/BannerCanvasOverlay.vue"
import { MAX_PREVIEW_HEIGHT, useBannerPreviewScale } from "@/Composables/useBannerPreviewScale"
import { BACKGROUND_KEY, fieldPathForEditableKey } from "@/Composables/useBannerCanvas"
import type { BannerScreenView, BannerWorkshop } from "@/types/BannerWorkshop"
import type { routeType } from "@/types/route"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPause, faPlay } from "@fal"

library.add(faPause, faPlay)

const props = defineProps<{
    modelValue: BannerWorkshop
    imagesUploadRoute: routeType
    ratio: string
    galleryRoute: {
        stock_images: routeType
        uploaded_images: routeType
    }
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: BannerWorkshop): void
}>()

const data = computed<BannerWorkshop>({
    get: () => props.modelValue,
    set: (value) => emits("update:modelValue", value)
})

const jumpToIndex = ref<string>("")
const screenView = ref<BannerScreenView>("desktop")
provide("screenView", screenView)

const hasSlides = computed(() => props.modelValue?.components?.some((slide) => slide.ulid != null))
const isSquare = computed(() => props.modelValue.type === "square")

const containerRef = ref<HTMLElement | null>(null)
const { needsScale, scaleValue, measureContainer } = useBannerPreviewScale(containerRef, toRef(props, "ratio"))

const scaledPreviewStyle = computed(() => {
    if (!needsScale.value) {
        return {}
    }

    return {
        transform: `scale(${scaleValue.value})`,
        transformOrigin: "top center",
        width: "100%"
    }
})

const isPlaying = ref(false)
const stageRef = ref<HTMLElement | null>(null)
const selectedKey = ref<string | null>(null)
const focusField = ref<string | null>(null)
const focusScope = ref<"slide" | "common">("slide")
const focusToken = ref(0)
const selectedSlideUlid = ref<string | null>(null)

const onCanvasSelect = ({
    key,
    scope,
    slideUlid
}: {
    key: string
    scope: "slide" | "common"
    slideUlid: string | null
}) => {
    selectedKey.value = key
    selectedSlideUlid.value = slideUlid
    focusScope.value = scope
    focusField.value = fieldPathForEditableKey(key, scope)
    focusToken.value++

    if (slideUlid) {
        jumpToIndex.value = slideUlid
    }
}

const SWIPER_CONTROLS = ".swiper-button-next, .swiper-button-prev, .swiper-pagination"

/**
 * The banner keeps its storefront links, which would navigate away from the
 * workshop on any click, so the stage swallows them and treats the click as a
 * selection of the slide background instead.
 */
const onStageClick = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null

    if (target?.closest("a[href]")) {
        event.preventDefault()
        event.stopPropagation()
    }

    if (isPlaying.value || target?.closest(SWIPER_CONTROLS)) {
        return
    }

    const slideUlid = target?.closest("[data-slide-ulid]")?.getAttribute("data-slide-ulid") ?? null

    if (!slideUlid) {
        return
    }

    onCanvasSelect({ key: BACKGROUND_KEY, scope: "slide", slideUlid })
}

const onCanvasEditText = ({
    key,
    scope,
    slideUlid,
    value
}: {
    key: string
    scope: "slide" | "common"
    slideUlid: string | null
    value: string
}) => {
    const path = key.split(".")
    const updated = cloneDeep(props.modelValue)

    if (scope === "common") {
        set(updated, ["common", ...path], value)
        data.value = updated
        return
    }

    const index = updated.components?.findIndex((slide) => slide.ulid === slideUlid) ?? -1

    if (index === -1) {
        return
    }

    const currentStage = get(updated, ["components", index, "layout", "centralStage"])

    if (!currentStage) {
        set(updated, ["components", index, "layout", "centralStage"], cloneDeep(updated.common?.centralStage) ?? {})
    }

    set(updated, ["components", index, "layout", ...path], value)
    data.value = updated
}

watch(
    () => props.modelValue.components.length,
    async () => {
        await nextTick()
        requestAnimationFrame(measureContainer)
    }
)

watch(screenView, () => {
    selectedKey.value = null
})

watch(isPlaying, (playing) => {
    if (playing) {
        selectedKey.value = null
    }
})
</script>

<template>
    <div v-if="hasSlides" class="w-full">
        <div class="flex items-center justify-end gap-x-2 pr-2">
            <Button
                v-tooltip="isPlaying ? ctrans('Pause to edit on the banner') : ctrans('Play the slideshow')"
                type="tertiary"
                size="xs"
                :icon="isPlaying ? 'fal fa-pause' : 'fal fa-play'"
                :label="isPlaying ? ctrans('Pause') : ctrans('Play')"
                @click="isPlaying = !isPlaying"
            />

            <ScreenView @screenView="(value) => (screenView = value as BannerScreenView)" />
        </div>

        <div
            class="flex pr-0.5 editor-class"
            :class="isSquare ? 'justify-start 2xl:justify-center' : 'justify-center'"
        >
            <div v-if="isSquare" class="w-full min-h-[250px] max-h-[400px]">
                <!-- The stage wraps the banner only: the overlay sits beside it so its own
                     nodes stay outside the MutationObserver watching the banner. -->
                <div class="relative h-full w-full">
                    <div ref="stageRef" class="h-full w-full" @click.capture="onStageClick" @dragstart.prevent>
                        <SliderSquare
                            :data="modelValue"
                            :jumpToIndex="jumpToIndex"
                            :view="screenView"
                            :ratio
                            :autoplay="isPlaying"
                        />
                    </div>
                    <BannerCanvasOverlay
                        v-if="!isPlaying"
                        :stage="stageRef"
                        :selectedKey="selectedKey"
                        @select="onCanvasSelect"
                        @editText="onCanvasEditText"
                    />
                </div>
            </div>

            <div
                v-else
                ref="containerRef"
                class="w-full max-w-[1200px] mx-auto overflow-hidden relative"
                :style="needsScale ? { height: `${MAX_PREVIEW_HEIGHT}px` } : {}"
            >
                <div :style="scaledPreviewStyle">
                    <div class="relative">
                        <div ref="stageRef" @click.capture="onStageClick" @dragstart.prevent>
                            <SliderLandscape
                                :data="modelValue"
                                :jumpToIndex="jumpToIndex"
                                :view="screenView"
                                :ratio
                                :autoplay="isPlaying"
                            />
                        </div>
                        <BannerCanvasOverlay
                            v-if="!isPlaying"
                            :stage="stageRef"
                            :scale="needsScale ? scaleValue : 1"
                            :selectedKey="selectedKey"
                            @select="onCanvasSelect"
                            @editText="onCanvasEditText"
                        />
                    </div>
                </div>
            </div>
        </div>

        <SlidesWorkshop
            v-model="data"
            class="clear-both mt-2 p-2.5"
            :bannerType="modelValue.type"
            :imagesUploadRoute="imagesUploadRoute"
            :screenView="screenView"
            :galleryRoute="galleryRoute"
            :ratio
            :focusField="focusField"
            :focusScope="focusScope"
            :focusSlideUlid="selectedSlideUlid"
            :focusToken="focusToken"
            @jumpToIndex="(value) => (jumpToIndex = value)"
        />
    </div>

    <div v-else>
        <SlidesWorkshopAddMode
            :data="modelValue"
            :imagesUploadRoute="imagesUploadRoute"
            :galleryRoute="galleryRoute"
            :ratio
        />
    </div>
</template>
