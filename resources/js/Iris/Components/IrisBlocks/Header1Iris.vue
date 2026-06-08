<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop"
import { inject, ref, computed } from "vue"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import { faPresentation, faCube, faText, faPaperclip } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from "@/Iris/Components/IrisButton.vue";
import { useFormatTime } from "@/Composables/useFormatTime"
import {
	faChevronRight,
	faSignOutAlt,
	faShoppingCart,
	faSearch,
	faChevronDown,
	faTimes,
	faPlusCircle,
	faBars,
	faUserCircle,
	faImage,
	faSignInAlt,
	faFileAlt,
	faUser,
	faEnvelope,
	faHeart
} from "@fas"
import LuigiSearch from "@/Components/CMS/LuigiSearch.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { ctrans } from "@/Composables/useTrans";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification"
import { set } from "lodash"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { faUserPlus } from "@far";

library.add(
	faPresentation,
	faCube,
	faText,
	faImage,
	faPaperclip,
	faChevronRight,
	faSignOutAlt,
	faShoppingCart,
	faHeart,
	faSearch,
	faChevronDown,
	faTimes,
	faPlusCircle,
	faBars,
	faUserCircle,
	faSignInAlt,
	faFileAlt
)

const props = defineProps<{
	fieldValue: {
		headerText: string
		logo: {
			alt: string,
			image: {
				source: object
			},
		}
		container: {
			properties: Object
		}
		button_1: {
			visible: boolean
			text: string
			container: {
				properties: Object
			}
		}
	}
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout = inject('layout', layoutStructure)
const isLoggedIn = computed(() => layout?.iris?.is_logged_in || false)
const loadingRedirect = ref(false)
const isLoadingLogout = ref(false)

const onClickLogout = () => {
	router.post(
		'/app/logout',
		{},
		{
			preserveScroll: true,
			onStart: () => {
				isLoadingLogout.value = true
			},
			onSuccess: () => {
				set(layout, ['iris', 'is_logged_in'], false)
				if (typeof window !== "undefined") {
					const storageIris = JSON.parse(localStorage.getItem('iris') || '{}')
					localStorage.setItem('iris', JSON.stringify({
						...storageIris,
						is_logged_in: false
					}))
				}
			},
			onError: () => {
				notify({
					title: ctrans("Something went wrong"),
					text: ctrans("Failed to logout"),
					type: "error"
				})
			},
			onFinish: () => {
				isLoadingLogout.value = false
			},
		}
	)
}


</script>

<template>
	<div id="header_1_iris" class="bg-white border-t border-sky-200 md:sticky top-0 z-50" :style="{
		...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
		margin: 0,
		padding: 0,
		...getStyles(fieldValue.container?.properties, screenType)
	}">
		<div class="max-w-[1800px] mx-auto h-[110px] px-6 flex items-center gap-8">
			<!-- Logo -->
			<div class="shrink-0 w-[180px]">
				<div class="relative aspect-[3/1.5]">
					<div v-if="loadingRedirect"
						class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 rounded">
						<LoadingIcon class="w-10 h-10" />
					</div>

					<component v-if="fieldValue?.logo?.image?.source"
						:is="fieldValue?.logo?.image?.source ? LinkIris : 'div'" @start="loadingRedirect = true"
						@finish="loadingRedirect = false" :canonical_url="fieldValue?.logo?.link?.canonical_url"
						:href="fieldValue?.logo?.link?.href" :type="fieldValue?.logo?.link?.type"
						:target="fieldValue?.logo?.link?.target || '_self'" class="block h-full">
						<Image :src="fieldValue?.logo?.image?.source" :alt="fieldValue?.logo?.alt" imageCover
							class="w-full h-full object-contain" />
					</component>
				</div>
			</div>

			<!-- Search -->
			<div class="flex-1 flex justify-center">
				<div class="w-full max-w-[760px]">
					<LuigiSearch v-if="layout.iris?.luigisbox_tracker_id" :fieldValueSearch="fieldValue?.search"
						id="luigi_header_2" />
				</div>
			</div>

			<!-- Right Menu -->
			<div class="shrink-0 h-full flex items-center gap-6">
				<!-- Mail -->
				<LinkIris v-if="isLoggedIn" href="/app/back-in-stocks" :type="'internal'"
					v-slot="{ isLoading } = { isLoading: false }">
					<button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
						v-tooltip="ctrans('Reminder back in stock')">
						<FontAwesomeIcon :icon="faEnvelope" class="text-[20px]" />
						<span class="text-sm font-medium">
							{{ layout.iris_variables?.back_in_stock_count }}
						</span>
					</button>
				</LinkIris>

				<!-- Wishlist -->
				<LinkIris v-if="isLoggedIn" href="/app/favourites" :type="'internal'"
					v-slot="{ isLoading } = { isLoading: false }">
					<button class="flex items-center gap-2 text-gray-600 hover:text-red-500 transition-colors"
						v-tooltip="ctrans('Favourites')">
						<FontAwesomeIcon :icon="faHeart" class="text-[20px]" />
						<span class="text-sm font-medium">
							<div class="button">
								{{ layout.iris_variables?.favourites_count }}
							</div>
						</span>
					</button>
				</LinkIris>


				<!-- Cart -->
				<LinkIris v-if="isLoggedIn" href="/app/basket" :type="'internal'"
					v-slot="{ isLoading } = { isLoading: false }">
					<button class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
						v-tooltip="ctrans('Cart count and amount')">
						<span class="button whitespace-nowrap"
							v-html="textReplaceVariables(`({{ cart_count }})`, layout.iris_variables)">
						</span>
						<FontAwesomeIcon :icon="faShoppingCart" class="text-[20px]" />
						<span class="button whitespace-nowrap"
							v-html="textReplaceVariables(`{{ cart_products_amount }}`, layout.iris_variables)">
						</span>
					</button>
				</LinkIris>

				<!-- Divider -->
				<div v-if="isLoggedIn" class="h-8 w-px bg-gray-200" />

				<!-- Logged In -->
				<template v-if="isLoggedIn">
					<div class="flex items-center gap-3">
						<div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100">
							<FontAwesomeIcon :icon="faUser" class="text-lg text-gray-500" />
						</div>

						<div class="leading-tight">
							<div class="flex items-center gap-2 text-sm font-medium">
								<span>{{ layout?.user?.username }}</span>

								<img v-if="layout?.offer_data?.amnesty || layout?.offer_data?.type === 'gr'"
									:src="`/assets/promo/gr-${layout.retina.organisation}.png`" alt="Gold Reward Logo"
									class="w-auto h-6" />
							</div>

							<template v-if="layout?.offer_data?.amnesty">
								<span
									class="inline-flex items-center gap-1 text-[11px] text-amber-500 whitespace-nowrap">
									<FontAwesomeIcon icon="fas fa-candle-holder" fixed-width aria-hidden="true" />

									{{
										ctrans('Until :amnestyUntil', {
											amnestyUntil: useFormatTime(
												layout?.offer_data?.amnesty_until,
												{ formatTime: 'MMM do' }
											)
										})
									}}
								</span>
							</template>

							<template v-else-if="layout?.offer_data?.type === 'gr'">
								<span class="inline-flex items-center gap-1 text-yellow-500">
									{{ layout?.offer_data?.label }}
									<GoldReward>
										<template #default>
											<div class="flex items-center">
												<FontAwesomeIcon icon="fas fa-medal" class="text-yellow-500" fixed-width
													aria-hidden="true" />

												<div
													class="relative inline-block w-20 h-3 ml-1 mt-1.5 mb-2 overflow-hidden align-middle rounded-sm bg-gray-200">
													<div class="absolute top-0 left-0 h-full transition-all duration-1000 ease-in-out bg-green-500"
														:class="{ xshimmer: true }" :style="{
															width: `${(layout?.offer_data?.meter?.[0] / layout?.offer_data?.meter?.[1]) * 100}%`
														}" />

													<div
														class="absolute inset-0 flex items-center justify-center font-medium text-black text-xxs">
														{{ Number(layout?.offer_data?.meter?.[0]).toFixed(0) }}
														/
														{{ Number(layout?.offer_data?.meter?.[1]).toFixed(0) }}
														days
													</div>
												</div>
											</div>
										</template>
									</GoldReward>
								</span>
							</template>
						</div>
					</div>

					<button v-tooltip="ctrans('Logout')"
						class="flex items-center justify-center text-[20px] text-gray-600 bg-gray-100 rounded-full hover:text-gray-900"
						@click="onClickLogout">
						<FontAwesomeIcon :icon="faSignOutAlt" />
					</button>
				</template>

				<!-- Guest -->
				<template v-else>
					<div class="flex items-center gap-3">
						<!-- Login -->
						<LinkIris :href="urlLoginWithRedirect()" :type="'internal'"
							v-slot="{ isLoading } = { isLoading: false }">
							<Button @click="() => urlLoginWithRedirect()" :label="ctrans('Login')"
								:icon="faSignInAlt"></Button>
						</LinkIris>

						<!-- Register -->
						<LinkIris href="/app/register" :type="'internal'" v-slot="{ isLoading } = { isLoading: false }">
							<Button :label="ctrans('Register')" :icon="faUserPlus" type="secondary"></Button>
						</LinkIris>
					</div>
				</template>
			</div>
		</div>
	</div>
</template>

<style scoped></style>
