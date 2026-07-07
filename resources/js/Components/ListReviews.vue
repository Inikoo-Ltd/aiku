<script setup lang="ts">
import { routeType } from "@/types/route"
import { Table as TableTS } from "@/types/Table"
import { GridProducts } from "@/Components/Product"
import Tag from "primevue/tag"
import Rating from "primevue/rating"
import Dialog from "primevue/dialog"
import { useFormatTime } from "@/Composables/useFormatTime"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
	faCube,
	faThumbsUp,
	faThumbsDown,
	faChevronCircleLeft,
	faChevronCircleRight,
} from "@fal"
import { faCircle, faDotCircle, faReply } from "@fas"
import { faEye, faEyeSlash, faFolder, faGameConsoleHandheld, faStar } from "@far"
import { inject, ref } from "vue"
import Image from "@/Common/Components/Image.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"

const props = withDefaults(
	defineProps<{
		data: any[] | TableTS
		tab: string
		readonly?: boolean
		review_settings?: any
		reaction_routes?: routeType
		showTagVisibleType?: boolean
	}>(),
	{
		showTagVisibleType: true,
		reaction_routes: {
			name: "retina.models.review.react"
		}
	}
)

const layout = inject("layout", {})

const scopeIcon = (scope: string) => {
	switch (scope) {
		case "family":
			return faFolder
		case "order":
			return faStar
		default:
			return faCube
	}
}

const scopeLabel = (scope: string) => {
	switch (scope) {
		case "family":
			return "Family Review"
		case "order":
			return "Order Review"
		default:
			return "Product Review"
	}
}

const previewImages = ref<any[]>([])
const previewIndex = ref(0)
const showImagePreview = ref(false)

const openImagePreview = (images: any[], index: number | string) => {
	previewImages.value = images
	previewIndex.value = Number(index)
	showImagePreview.value = true
}

const prevPreviewImage = () => {
	previewIndex.value =
		(previewIndex.value - 1 + previewImages.value.length) % previewImages.value.length
}

const nextPreviewImage = () => {
	previewIndex.value = (previewIndex.value + 1) % previewImages.value.length
}

const showOriginal = ref<Record<number, boolean>>({})

const hasMessageTranslation = (review: any) =>
	!!review?.message_translated && review.message_translated !== review.message

const hasReplyTranslation = (reply: any) =>
	!!reply?.message_translated && reply.message_translated !== reply.message

const hasTranslation = (review: any) =>
	hasMessageTranslation(review) || hasReplyTranslation(review?.reply)

const displayMessage = (review: any) =>
	hasMessageTranslation(review) && !showOriginal.value[review.review_id]
		? review.message_translated
		: review.message

const displayReply = (review: any) =>
	hasReplyTranslation(review.reply) && !showOriginal.value[review.review_id]
		? review.reply.message_translated
		: review.reply?.message

const toggleTranslation = (review: any) => {
	showOriginal.value[review.review_id] = !showOriginal.value[review.review_id]
}

const reactingKeys = ref<Record<string, boolean>>({})

const toggleReaction = (item: any, target: "review" | "review_reply", isLike: boolean) => {
	const review = item.review
	if (!review?.review_id || props.readonly) {
		return
	}

	const reactionKey = `${review.review_id}-${target}`
	if (reactingKeys.value[reactionKey]) {
		return
	}

	const likeField = target === "review" ? "likes" : "replay_likes"
	const dislikeField = target === "review" ? "dislikes" : "replay_dislikes"

	review[likeField] = (review[likeField] ?? 0) + (isLike ? 1 : 0)
	review[dislikeField] = (review[dislikeField] ?? 0) + (isLike ? 0 : 1)

	router.post(
		route(props.reaction_routes.name, { review: review.review_id }),
		{
			target: target,
			type: isLike ? "like" : "dislike",
		},
		{
			preserveScroll: true,
			preserveState: true,
			onStart: () => {
				reactingKeys.value[reactionKey] = true
			},
			onError: () => {
				review[likeField] -= isLike ? 1 : 0
				review[dislikeField] -= isLike ? 0 : 1
			},
			onFinish: () => {
				delete reactingKeys.value[reactionKey]
			},
		}
	)
}


</script>

<template>
	<div class="p-4 px-0">
		<GridProducts :resource="data" :preserve-scroll="true" :name="tab" :label="'reviews'"
			:gridClass="'lg:grid-cols-1 xl:grid-cols-1 grid grid-cols-1 gap-0 rating'">
			<template #card="{ item }">
				<article
					class="rounded-xl border border-gray-200 bg-white p-4 transition-all duration-200 hover:border-gray-300 hover:shadow-md">
					<!-- Header -->
					<div class="flex gap-3">
						<!-- Scope -->
						<div v-tooltip="`Review ${item.scope}`"
							class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100">
							<FontAwesomeIcon :icon="scopeIcon(item.scope)" class="text-sm text-gray-500" />
						</div>

						<!-- Content -->
						<div class="min-w-0 flex-1">
							<!-- Row 1 -->
							<div class="flex items-start justify-between gap-4">
								<div class="min-w-0">
									<div class="flex flex-wrap items-center gap-2">
										<span class="truncate text-sm font-semibold text-gray-900">
											{{ item.name }}
										</span>

										<Tag v-if="showTagVisibleType" rounded
											:severity="item.review.is_public ? 'success' : 'secondary'"
											class="text-[10px]">
											<template #icon>
												<FontAwesomeIcon :icon="item.review.is_public ? faEye : faEyeSlash" />
											</template>

											{{ item.review.is_public ? 'Public' : 'Private' }}
										</Tag>
									</div>
								</div>

								<div class="flex shrink-0 items-center gap-2">
									<Rating :modelValue="item.review.rating" readonly :cancel="false" />

									<span class="text-sm font-semibold text-amber-600">
										{{ item.review.rating }}/5
									</span>
								</div>
							</div>

							<!-- Row 2 -->
							<div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-400">
								<span>{{ item.code }}</span>
								<FontAwesomeIcon :icon="faCircle" class="text-[5px]" />
								<span>{{ useFormatTime(item.created_at) }}</span>
							</div>
						</div>
					</div>

					<!-- Review -->
					<div class="mt-3 space-y-3">
						<!-- Review -->
						<p class="whitespace-pre-line text-sm leading-6 text-gray-700">
							{{ displayMessage(item.review) }}
						<!-- 	<div v-if="layout?.app?.environment == 'local'">
  							   <span class="text-red-500">{{ item?.review?.message }}</span>
							   <span class="text-green-500">{{ item?.review?.message_translated }}</span>
							</div> -->
						</p>

						<!-- Images -->
						<div v-if="item.review.review_images?.length" class="flex gap-3">
							<slot name="image-item" :item="item" :openImagePreview="openImagePreview">
								<button type="button" v-for="(image, index) in item.review.review_images"
									:key="image.id ?? index"
									class="group relative aspect-square w-12 h-12 cursor-zoom-in overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
									@click="openImagePreview(item.review.review_images, index)">
									<Image :src="image.media_url" :alt="image.name" :imageCover="true"
										class="h-full w-full flex items-center justify-center transition duration-300 group-hover:scale-105" />
								</button>
							</slot>
						</div>

						<div v-if="hasTranslation(item.review)" @click="toggleTranslation(item.review)"
							class="flex w-full justify-end text-gray-400 hover:text-gray-700 cursor-pointer text-xs">
							{{ showOriginal[item.review.review_id] ? ctrans("See translation") : ctrans("See original") }}
						</div>
					</div>

					<!-- Actions -->
					<div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
						<div class="flex items-center gap-1">
							<button :disabled="reactingKeys[`${item.review.review_id}-review`]"
								@click="() => toggleReaction(item, 'review', true)" :class="[
									'flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-all duration-200 disabled:opacity-50',
									item.review_reaction === true
										? 'border-blue-200 bg-blue-50 text-blue-600 shadow-sm'
										: 'border-transparent text-gray-500 hover:border-gray-200 hover:bg-gray-100',
								]">
								<FontAwesomeIcon :icon="faThumbsUp" />

								<span>
									{{ item.review.likes ?? 0 }}
								</span>
							</button>

							<button :disabled="reactingKeys[`${item.review.review_id}-review`]"
								@click="() => toggleReaction(item, 'review', false)" :class="[
									'flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-all duration-200 disabled:opacity-50',
									item.review_reaction === false
										? 'border-red-200 bg-red-50 text-red-600 shadow-sm'
										: 'border-transparent text-gray-500 hover:border-gray-200 hover:bg-gray-100',
								]">
								<FontAwesomeIcon :icon="faThumbsDown" />

								<span>
									{{ item.review.dislikes ?? 0 }}
								</span>
							</button>
						</div>

						  
                        <div class="mt-auto text-[11px] text-gray-400 w-fit">
                            <AddressLocation :data="item['location']" :use_flag="item?.location[1] != layout?.iris?.shop?.location[1]" />
                        </div>
					</div>

					<!-- Reply -->
					<div v-if="item.review.reply" class="mt-4 border-l-2 border-orange-300 pl-4">
						<div class="flex gap-3">
							<div
								class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white">

								<img v-if="layout?.iris?.website?.logo?.avif" :src="layout.iris.website.logo.avif"
									src="logo" />
								<FontAwesomeIcon v-else :icon="faReply" class="text-[10px]" />
							</div>

							<div class="min-w-0 flex-1">
								<!-- Reply Header -->
								<div class="flex flex-wrap items-center gap-2">
									<span class="text-sm font-semibold text-gray-900">
										<!-- {{ item.review.reply.contact_name }} -->
										{{
											review_settings.show_staff_who_reply
												? item.review.reply.contact_name
												: layout.iris.shop.name
										}}
									</span>

									<span class="ml-auto text-[11px] text-gray-400">
										{{ useFormatTime(item.review.reply.at) }}
									</span>
								</div>

								<!-- Reply Body -->
								<p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">
									{{ displayReply(item.review) }}

									<!-- <div v-if="layout.app.environment == 'local'">
  							   			<span class="text-red-500">{{ item?.review?.reply?.reply }}</span>
										<span class="text-green-500">{{ item?.review?.reply?.reply_translated }}</span>
									</div> -->
								</p>

								<div v-if="hasReplyTranslation(item.review.reply)"
									@click="toggleTranslation(item.review)"
									class="mt-1 flex w-full justify-end text-gray-400 hover:text-gray-700 cursor-pointer text-xs">
									{{ showOriginal[item.review.review_id] ? ctrans("See translation") : ctrans("See original") }}
								</div>

								<!-- Reply Actions -->
								<div class="mt-2 flex items-center gap-1">
									<button :disabled="reactingKeys[`${item.review.review_id}-reply`]"
										@click="() => toggleReaction(item, 'review_reply', true)" :class="[
											'flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-all duration-200 disabled:opacity-50',
											item.reply_reaction === true
												? 'border-blue-200 bg-blue-50 text-blue-600 shadow-sm'
												: 'border-transparent text-gray-500 hover:border-gray-200 hover:bg-gray-100'
										]">
										<FontAwesomeIcon :icon="faThumbsUp" />

										<span>
											{{ item.review.reply.likes ?? 0 }}
										</span>
									</button>

									<button :disabled="reactingKeys[`${item.review.review_id}-reply`]"
										@click="() => toggleReaction(item, 'review_reply', false)" :class="[
											'flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-all duration-200 disabled:opacity-50',
											item.reply_reaction === false
												? 'border-red-200 bg-red-50 text-red-600 shadow-sm'
												: 'border-transparent text-gray-500 hover:border-gray-200 hover:bg-gray-100'
										]">
										<FontAwesomeIcon :icon="faThumbsDown" />

										<span>
											{{ item.review.reply.dislikes ?? 0 }}
										</span>
									</button>
								</div>
							</div>
						</div>
					</div>
				</article>
			</template>
		</GridProducts>

		<Dialog v-model:visible="showImagePreview" modal dismissableMask
			class="w-full max-w-3xl !border-0 !bg-transparent !shadow-none">
			<div class="relative flex w-full flex-col items-center justify-center">

				<div class="mb-1 block max-h-[80vh] min-h-[400px] w-full rounded">
					<slot name="image-dialog" :images="previewImages[previewIndex]">
						<Image :src="previewImages[previewIndex]?.media_url" :alt="previewImages[previewIndex]?.name"
							:imageCover="true" :style="{ objectFit: 'contain' }" />
					</slot>
				</div>

				<template v-if="previewImages.length > 1">
					<button type="button" class="absolute left-2 top-1/2 z-40 -translate-y-1/2 text-3xl text-white"
						@click="prevPreviewImage">
						<FontAwesomeIcon :icon="faChevronCircleLeft" />
					</button>

					<button type="button" class="absolute right-2 top-1/2 z-40 -translate-y-1/2 text-3xl text-white"
						@click="nextPreviewImage">
						<FontAwesomeIcon :icon="faChevronCircleRight" />
					</button>

					<div class="mt-2 text-xs text-white">
						{{ previewIndex + 1 }} / {{ previewImages.length }}
					</div>
				</template>
			</div>
		</Dialog>
	</div>
</template>
<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
	color: #f59e0b !important;
}

:deep(.p-dialog-mask) {
	background-color: rgba(0, 0, 0, 0.9) !important;
}
</style>
