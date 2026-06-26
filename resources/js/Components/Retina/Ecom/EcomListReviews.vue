<script setup lang="ts">
import { routeType } from "@/types/route"
import { Table as TableTS } from "@/types/Table"
import { GridProducts } from "@/Components/Product"
import Card from "primevue/card"
import Tag from "primevue/tag"
import Rating from "primevue/rating"
import Avatar from "primevue/avatar"
import { useFormatTime } from "@/Composables/useFormatTime"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube, faStore, faLayerGroup, faThumbsUp, faThumbsDown } from "@fal"
import { faBadgeCheck, faReply } from "@fas"
import { faEye, faEyeSlash, faStar } from "@far"

defineProps<{
	data: any[] | TableTS
	tab: string
	updateRoute: routeType
	state?: string
	readonly?: boolean
}>()

const scopeIcon = (scope: string) => {
	switch (scope) {
		case "family":
			return faLayerGroup
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

const toggleReaction = (item: any, type: "like" | "dislike") => {
    /* const review = item.review
    if (!review?.review_id || props.readonly) {
        return
    }

    const isLiked = review.is_liked
    const isDisliked = review.is_disliked

    if (type === "like") {
        review.is_liked = !isLiked
        review.is_disliked = false
    } else {
        review.is_disliked = !isDisliked
        review.is_liked = false
    }

    router.post(
        route("retina.ecom.reviews.reaction", { review: review.review_id }),
        { type, value: type === "like" ? review.is_liked : review.is_disliked },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                loadingReaction.value[review.review_id] = type
            },
            onError: () => {
                review.is_liked = isLiked
                review.is_disliked = isDisliked
            },
            onFinish: () => {
                delete loadingReaction.value[review.review_id]
            },
        }
    ) */
}
</script>

<template>
	<div class="p-4">
		<GridProducts
			:resource="data"
			:preserve-scroll="true"
			:name="tab"
			:gridClass="'lg:grid-cols-1 xl:grid-cols-1 grid grid-cols-1 gap-0 rating'">
			<template #card="{ item }">
				<article
					class="rounded-xl border border-gray-200 bg-white p-4 transition-all duration-200 hover:border-gray-300 hover:shadow-md">
					<!-- Header -->
					<div class="flex items-start justify-between gap-3">
						<div class="flex min-w-0 gap-3">
							<!-- Scope -->
							<div
								class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100">
								<FontAwesomeIcon
									:icon="scopeIcon(item.scope)"
									class="text-sm text-gray-500" />
							</div>

							<!-- Title -->
							<div class="min-w-0 flex-1">
								<div class="flex flex-wrap items-center gap-2">
									<h3 class="truncate text-sm font-semibold text-gray-900">
										{{ item.name }}
									</h3>

									<Tag
										rounded
										:severity="item.is_public ? 'success' : 'secondary'"
										class="text-[10px]">
										<template #icon>
											<FontAwesomeIcon
												:icon="item.is_public ? faEye : faEyeSlash" />
										</template>

										{{ item.is_public ? "Public" : "Private" }}
									</Tag>
								</div>

								<div
									class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-400">
									<span>{{ item.code }}</span>

									<span>•</span>

									<span>{{ useFormatTime(item.created_at) }}</span>
								</div>
							</div>
						</div>

						<div class="flex gap-3 item-center">
							<Rating :modelValue="item.review.rating" readonly :cancel="false" />

							<div class="text-xs font-medium text-amber-600">
								{{ item.review.rating }}/5
							</div>
						</div>
					</div>

					<!-- Review -->
					<div class="mt-3">
						<p class="whitespace-pre-line text-sm leading-6 text-gray-700">
							{{ item.review.message }}
						</p>
					</div>

					<!-- Actions -->
					<div
						class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
						<div class="flex items-center gap-1">
							<button
								class="flex items-center gap-1 rounded-md px-2 py-1 text-xs text-gray-500 transition hover:bg-gray-100">
								<FontAwesomeIcon :icon="faThumbsUp" />

								<span>
									{{ item.review.likes ?? 0 }}
								</span>
							</button>

							<button
								class="flex items-center gap-1 rounded-md px-2 py-1 text-xs text-gray-500 transition hover:bg-gray-100">
								<FontAwesomeIcon :icon="faThumbsDown" />

								<span>
									{{ item.review.dislikes ?? 0 }}
								</span>
							</button>
						</div>
					</div>

					<!-- Reply -->
					<div v-if="item.review.reply" class="mt-4 border-l-2 border-orange-300 pl-4">
						<div class="flex gap-3">
							<div
								class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white">
								<FontAwesomeIcon :icon="faReply" class="text-[10px]" />
							</div>

							<div class="min-w-0 flex-1">
								<!-- Reply Header -->
								<div class="flex flex-wrap items-center gap-2">
									<span class="text-sm font-semibold text-gray-900">
										{{ item.review.reply.contact_name }}
									</span>

									<Tag severity="warn" rounded class="text-[9px]"> Official </Tag>

									<span class="ml-auto text-[11px] text-gray-400">
										{{ useFormatTime(item.review.reply.at) }}
									</span>
								</div>

								<!-- Reply Body -->
								<p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">
									{{ item.review.reply.message }}
								</p>

								<!-- Reply Actions -->
								<div class="mt-2 flex items-center gap-1">
									<button
										class="flex items-center gap-1 rounded-md px-2 py-1 text-xs text-orange-600 transition hover:bg-orange-100">
										<FontAwesomeIcon :icon="faThumbsUp" />

										<span>
											{{ item.review.reply.likes ?? 0 }}
										</span>
									</button>

									<button
										class="flex items-center gap-1 rounded-md px-2 py-1 text-xs text-orange-600 transition hover:bg-orange-100">
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
	</div>
</template>
<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
	color: #f59e0b !important;
}
</style>
