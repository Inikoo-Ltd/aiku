<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from "vue"
import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronLeft, faChevronRight, faPlay, faTimes, faVideo, faVolumeMute, faVolumeUp } from "@fas"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import IrisButton from "@/Iris/Components/IrisButton.vue"
import type { VideoCarouselFieldValue, VideoCarouselItem } from "@/Components/CMS/Webpage/VideoCarousel1/types"

type ScreenType = "mobile" | "tablet" | "desktop"

const props = defineProps<{
	fieldValue: VideoCarouselFieldValue
	screenType: ScreenType
	isEditing?: boolean
}>()

const emits = defineEmits<{
	(e: "tileClick", index: number): void
}>()

const settings = computed(() => props.fieldValue?.settings ?? {})
const videos = computed<VideoCarouselItem[]>(() =>
	(props.fieldValue?.videos ?? []).filter((item) => item?.source?.video_url || item?.source?.tiktok_id)
)

const slidesPerView = computed(() => Number(settings.value.slidesPerView?.[props.screenType] ?? (props.screenType === "mobile" ? 2 : 4)))
const breakpoints = computed(() => ({
	0: { slidesPerView: Number(settings.value.slidesPerView?.mobile ?? 2) },
	768: { slidesPerView: Number(settings.value.slidesPerView?.tablet ?? 3) },
	1200: { slidesPerView: Number(settings.value.slidesPerView?.desktop ?? 4) },
}))
const spaceBetween = computed(() => Number(settings.value.spaceBetween ?? 16))
const mostSlidesPerView = computed(() => Math.max(...Object.values(breakpoints.value).map((breakpoint) => breakpoint.slidesPerView)))
const isLooping = computed(() => settings.value.loop !== false && videos.value.length > mostSlidesPerView.value)
const isSwiperLocked = ref(false)
const showArrows = computed(() => settings.value.arrows !== false && videos.value.length > 1 && !isSwiperLocked.value)
const isMounted = ref(false)
onMounted(() => (isMounted.value = true))

const carouselId = "video-carousel-" + useId()
const preInitSlideCss = computed(() => {
	const rule = (count: number) =>
		`#${carouselId} .swiper:not(.swiper-initialized) .swiper-slide{width:calc((100% - ${(count - 1) * spaceBetween.value}px)/${count});margin-right:${spaceBetween.value}px;flex-shrink:0}`
	return rule(breakpoints.value[0].slidesPerView)
		+ `@media(min-width:768px){${rule(breakpoints.value[768].slidesPerView)}}`
		+ `@media(min-width:1200px){${rule(breakpoints.value[1200].slidesPerView)}}`
})
const isAutoplay = computed(() => settings.value.autoplay !== false)
const tileStyle = computed(() => getStyles(props.fieldValue?.tile?.properties, props.screenType))

const swiperKey = ref(0)
const swiperInstance = ref<any>(null)
watch(() => [settings.value, props.screenType, videos.value.length], () => swiperKey.value++, { deep: true })

const tileVideos = new Map<number, HTMLVideoElement>()
let observer: IntersectionObserver | null = null

const playWhenVisible = (entries: IntersectionObserverEntry[]) => {
	for (const entry of entries) {
		const video = entry.target as HTMLVideoElement
		if (entry.isIntersecting && isAutoplay.value && openIndex.value === null) {
			video.play().catch(() => null)
		} else {
			video.pause()
		}
	}
}

const registerTileVideo = (element: any, index: number) => {
	if (typeof window === "undefined" || !("IntersectionObserver" in window)) {
		return
	}
	observer ??= new IntersectionObserver(playWhenVisible, { threshold: 0.5 })
	const previous = tileVideos.get(index)
	if (previous && previous !== element) {
		observer.unobserve(previous)
	}
	if (element) {
		tileVideos.set(index, element)
		observer.observe(element)
	} else {
		tileVideos.delete(index)
	}
}

const openIndex = ref<number | null>(null)
const isPopupMuted = ref(false)
const openItem = computed(() => (openIndex.value === null ? null : videos.value[openIndex.value] ?? null))

const tiktokPlayerUrl = (item: VideoCarouselItem, autoplay: boolean) =>
	`https://www.tiktok.com/player/v1/${item.source.tiktok_id}?autoplay=${autoplay ? 1 : 0}&loop=1&music_info=0&description=0&rel=0`

const onKeydown = (event: KeyboardEvent) => {
	if (event.key === "Escape") closePopup()
	if (event.key === "ArrowRight") stepPopup(1)
	if (event.key === "ArrowLeft") stepPopup(-1)
}

const openPopup = (index: number) => {
	if (props.isEditing) {
		emits("tileClick", (props.fieldValue?.videos ?? []).indexOf(videos.value[index]))
		return
	}
	openIndex.value = index
	isPopupMuted.value = false
	tileVideos.forEach((video) => video.pause())
	document.addEventListener("keydown", onKeydown)
	document.body.style.overflow = "hidden"
}

const closePopup = () => {
	openIndex.value = null
	document.removeEventListener("keydown", onKeydown)
	document.body.style.overflow = ""
	if (isAutoplay.value) {
		tileVideos.forEach((video) => {
			const box = video.getBoundingClientRect()
			if (box.bottom > 0 && box.top < window.innerHeight && box.right > 0 && box.left < window.innerWidth) {
				video.play().catch(() => null)
			}
		})
	}
}

const stepPopup = (step: number) => {
	if (openIndex.value === null || videos.value.length === 0) return
	openIndex.value = (openIndex.value + step + videos.value.length) % videos.value.length
}

const togglePopupMute = () => {
	isPopupMuted.value = !isPopupMuted.value
}

onBeforeUnmount(() => {
	observer?.disconnect()
	document.removeEventListener("keydown", onKeydown)
	if (openIndex.value !== null) document.body.style.overflow = ""
})
</script>

<template>
	<div :id="carouselId">
		<component :is="'style'">{{ preInitSlideCss }}</component>
		<slot name="heading" />

		<div v-if="videos.length" class="relative">
			<button v-if="showArrows" type="button" :aria-label="ctrans('Previous video')"
				class="absolute left-0 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-600 shadow hover:bg-white"
				@click.stop="swiperInstance?.slidePrev()">
				<FontAwesomeIcon :icon="faChevronLeft" fixed-width />
			</button>

			<div :class="showArrows ? 'px-12' : ''">
			<Swiper :key="swiperKey" :slides-per-view="slidesPerView" :space-between="spaceBetween"
				:breakpoints="breakpoints" :loop="isLooping" class="w-full"
				@swiper="(swiper: any) => { swiperInstance = swiper; isSwiperLocked = !!swiper.isLocked }"
				@lock="isSwiperLocked = true" @unlock="isSwiperLocked = false">
				<SwiperSlide v-for="(item, index) in videos" :key="(item.source.video_url || item.source.tiktok_id) + '-' + index">
					<button type="button" class="group relative block aspect-[9/16] w-full overflow-hidden bg-gray-900"
						:style="tileStyle" :aria-label="ctrans('Play video')" @click="openPopup(index)">
						<video v-if="item.source.video_url" :ref="(element) => registerTileVideo(element, index)"
							:src="item.source.poster_url ? item.source.video_url : item.source.video_url + '#t=0.1'"
							:poster="item.source.poster_url || undefined"
							:preload="item.source.poster_url ? 'none' : 'metadata'"
							muted loop playsinline disablepictureinpicture
							class="pointer-events-none absolute inset-0 h-full w-full object-cover" />
						<img v-else-if="item.source.poster_url" :src="item.source.poster_url" :alt="item.link_label || ''"
							loading="lazy" class="absolute inset-0 h-full w-full object-cover" />
						<div v-else class="absolute inset-0 flex items-center justify-center text-4xl text-gray-500">
							<FontAwesomeIcon :icon="faVideo" fixed-width />
						</div>

						<span class="absolute inset-0 flex items-center justify-center transition group-hover:opacity-100"
							:class="item.source.video_url && isAutoplay ? 'opacity-0' : 'opacity-90'">
							<span class="flex h-12 w-12 items-center justify-center rounded-full bg-black/45 text-white">
								<FontAwesomeIcon :icon="faPlay" fixed-width />
							</span>
						</span>
					</button>
				</SwiperSlide>
			</Swiper>
			</div>

			<button v-if="showArrows" type="button" :aria-label="ctrans('Next video')"
				class="absolute right-0 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-600 shadow hover:bg-white"
				@click.stop="swiperInstance?.slideNext()">
				<FontAwesomeIcon :icon="faChevronRight" fixed-width />
			</button>
		</div>

		<div v-else-if="isEditing" class="flex aspect-[4/1] w-full items-center justify-center rounded border-2 border-dashed border-gray-300 text-sm text-gray-400">
			<FontAwesomeIcon :icon="faVideo" class="mr-2" fixed-width />
			{{ ctrans("Add videos in the block settings") }}
		</div>

		<slot name="button" />

		<Teleport v-if="isMounted" to="body">
			<div v-if="openItem" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4"
				role="dialog" aria-modal="true" @click.self="closePopup">
				<button type="button" :aria-label="ctrans('Close')"
					class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-xl text-white hover:bg-white/25"
					@click="closePopup">
					<FontAwesomeIcon :icon="faTimes" fixed-width />
				</button>

				<button v-if="videos.length > 1" type="button" :aria-label="ctrans('Previous video')"
					class="absolute left-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25 md:left-8"
					@click="stepPopup(-1)">
					<FontAwesomeIcon :icon="faChevronLeft" fixed-width />
				</button>

				<div class="flex max-h-full w-full max-w-[min(420px,calc((100vh-6rem)*9/16))] flex-col items-center gap-3">
					<div class="relative aspect-[9/16] w-full overflow-hidden rounded-lg bg-black">
						<iframe v-if="openItem.source.tiktok_id && !openItem.source.video_url" :key="openItem.source.tiktok_id"
							:src="tiktokPlayerUrl(openItem, true)" class="absolute inset-0 h-full w-full"
							allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen />
						<template v-else>
							<video :key="openItem.source.video_url!" :src="openItem.source.video_url!"
								:poster="openItem.source.poster_url || undefined" :muted="isPopupMuted"
								autoplay loop playsinline controls controlslist="nodownload noplaybackrate"
								class="absolute inset-0 h-full w-full object-contain" />
							<div class="absolute left-3 top-3 flex gap-2">
								<button type="button" :aria-label="isPopupMuted ? ctrans('Sound on') : ctrans('Sound off')"
									class="flex h-9 w-9 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
									@click="togglePopupMute">
									<FontAwesomeIcon :icon="isPopupMuted ? faVolumeMute : faVolumeUp" fixed-width />
								</button>
							</div>
						</template>
					</div>

					<a v-if="openItem.link?.href" :href="openItem.link.href" :target="openItem.link.target || undefined" class="w-full">
						<IrisButton full :label="openItem.link_label || ctrans('Shop now')" />
					</a>
				</div>

				<button v-if="videos.length > 1" type="button" :aria-label="ctrans('Next video')"
					class="absolute right-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25 md:right-8"
					@click="stepPopup(1)">
					<FontAwesomeIcon :icon="faChevronRight" fixed-width />
				</button>
			</div>
		</Teleport>
	</div>
</template>
