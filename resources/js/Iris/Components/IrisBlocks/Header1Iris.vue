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
	faHeart,
	faMeat,
	faMedal
} from "@fas"
import IrisSearch from "@/Iris/Components/IrisSearch.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { ctrans } from "@/Composables/useTrans";
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { faUserPlus } from "@far";
import GoldReward from "@/Components/Utils/GoldReward.vue"
import { useOpenBasketPanelOnClick } from "@/Iris/Composables/useSidePanel"
import { useIrisLogout } from "@/Iris/Composables/useIrisLogout"

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

const openBasketPanelOnClick = useOpenBasketPanelOnClick()
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
	screenType?: 'mobile' | 'tablet' | 'desktop'
}>()

const layout = inject('layout', layoutStructure)
const isLoggedIn = computed(() => layout?.iris?.is_logged_in || false)
const isShopB2B = computed(() => layout?.retina?.type === 'b2b')
const displayName = computed(() => layout?.user?.contact_name?.trim() || layout?.user?.username?.split('@')[0] || '')
const loginUrl = computed(() => urlLoginWithRedirect())
const loadingRedirect = ref(false)
const { isLoadingLogout, logout: onClickLogout } = useIrisLogout(layout)


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
							:imgAttributes="{ loading: 'eager', decoding: 'async' }"
							class="w-full h-full object-contain" />
					</component>
				</div>
			</div>

			<!-- Search -->
			<div class="flex-1 flex justify-center">
				<div class="w-full max-w-[760px]">
					<IrisSearch :fieldValueSearch="fieldValue?.search" id="iris_search_header_1" />
				</div>
			</div>

			<!-- Right Menu -->
			<div class="shrink-0 h-full flex items-center gap-6">
				<!-- My Interest -->
				<LinkIris v-if="isLoggedIn && isShopB2B" href="/app/interest/favourites" :type="'internal'"
					v-slot="{ isLoading } = { isLoading: false }">
					<button
						class="flex items-center gap-2 text-gray-600 hover:text-red-500 transition-colors disabled:opacity-60 disabled:cursor-wait"
						:disabled="isLoading" v-tooltip="ctrans('My Interest')">
						<LoadingIcon v-if="isLoading" class="text-[20px]" />
						<FontAwesomeIcon v-else :icon="faHeart" class="text-[20px]" fixed-width />
						<span class="text-sm font-medium">
							{{ ctrans('My Interests') }}
						</span>
					</button>
				</LinkIris>


				<!-- Cart -->
				<LinkIris v-if="isLoggedIn && isShopB2B" href="/app/basket" :type="'internal'"
					v-slot="{ isLoading } = { isLoading: false }">
					<button
						class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-60 disabled:cursor-wait"
						:disabled="isLoading" v-tooltip="ctrans('Cart count and amount')" @click="openBasketPanelOnClick">
						<span class="button whitespace-nowrap"
							v-html="textReplaceVariables(`({{ cart_count }})`, layout.iris_variables)">
						</span>
						<LoadingIcon v-if="isLoading" class="text-[20px]" />
						<FontAwesomeIcon v-else :icon="faShoppingCart" class="text-[20px]" fixed-width />
						<span class="button whitespace-nowrap"
							v-html="textReplaceVariables(`{{ cart_products_amount }}`, layout.iris_variables)">
						</span>
					</button>
				</LinkIris>

				<!-- Divider -->
				<div v-if="isLoggedIn && isShopB2B" class="h-8 w-px bg-gray-200" />

				<!-- Logged In -->
				<template v-if="isLoggedIn">
					<LinkIris href="/app/dashboard" :type="'internal'"
						v-slot="{ isLoading } = { isLoading: false }">
					<div class="flex items-center gap-3 min-w-0 cursor-pointer group"
						:class="{ 'cursor-wait': isLoading }">
							<div class="relative shrink-0">
								<div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 overflow-hidden">
									<LoadingIcon v-if="isLoading" class="text-lg text-gray-500" />
									<Image v-else-if="layout?.user?.avatar_thumbnail" :src="layout.user.avatar_thumbnail"
										imageCover class="w-full h-full object-cover" />
									<FontAwesomeIcon v-else :icon="faUser" class="text-lg text-gray-500" fixed-width />
								</div>
								<component :is="layout?.offer_data?.amnesty ? 'span' : GoldReward"
									v-if="layout?.offer_data?.amnesty || layout?.offer_data?.type === 'gr'"
									class="absolute -top-1.5 -right-1.5">
									<span class="flex items-center justify-center w-5 h-5 rounded-full bg-white ring-1 ring-gray-200 shadow-sm">
										<FontAwesomeIcon :icon="faMedal" class="text-[11px]"
											:class="layout?.offer_data?.amnesty ? 'text-[#E87928]' : 'text-yellow-500'"
											fixed-width aria-hidden="true" />
									</span>
								</component>
							</div>

						<div class="leading-tight min-w-0">
							<div class="flex items-center gap-2 text-sm font-medium">
								<span class="truncate max-w-[9rem] xl:max-w-[14rem] group-hover:underline" :title="displayName">{{ displayName }}</span>

							</div>

							<template v-if="layout?.offer_data?.amnesty">
								<span
									class="inline-flex items-center gap-1 text-[11px] text-[#E87928] whitespace-nowrap">
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
								<span class="inline-flex items-center gap-1.5 text-yellow-500 whitespace-nowrap">
									{{ layout?.offer_data?.label }}
									<span class="relative w-16 h-1.5 overflow-hidden rounded-full bg-gray-200">
										<span class="absolute top-0 left-0 h-full rounded-full transition-all duration-1000 ease-in-out bg-green-500"
											:style="{ width: `${(layout?.offer_data?.meter?.[0] / layout?.offer_data?.meter?.[1]) * 100}%` }" />
									</span>
									<span class="text-xs tabular-nums text-gray-500">
										{{ ctrans(':left / :total days', {
											left: Number(layout?.offer_data?.meter?.[0]).toFixed(0),
											total: Number(layout?.offer_data?.meter?.[1]).toFixed(0)
										}) }}
									</span>
								</span>
							</template>
						</div>
					</div>
					</LinkIris>

					<button v-tooltip="ctrans('Logout')"
						class="flex items-center justify-center text-[20px] text-gray-600 bg-gray-100 rounded-full hover:text-gray-900 disabled:opacity-60 disabled:cursor-wait"
						:disabled="isLoadingLogout" @click="onClickLogout">
						<LoadingIcon v-if="isLoadingLogout" />
						<FontAwesomeIcon v-else :icon="faSignOutAlt" fixed-width />
					</button>
				</template>

				<!-- Guest -->
				<template v-else>
					<div class="flex items-center gap-3">
						<!-- Login -->
						<LinkIris :href="loginUrl" :type="'internal'"
							v-slot="{ isLoading } = { isLoading: false }">
							<Button :label="ctrans('Login')" :icon="faSignInAlt" :loading="isLoading"></Button>
						</LinkIris>

						<!-- Register -->
						<LinkIris href="/app/register" :type="'internal'" v-slot="{ isLoading } = { isLoading: false }">
							<Button :label="ctrans('Register')" :icon="faUserPlus" type="secondary"
								:loading="isLoading"></Button>
						</LinkIris>
					</div>
				</template>
			</div>
		</div>
	</div>
</template>

<style scoped></style>
