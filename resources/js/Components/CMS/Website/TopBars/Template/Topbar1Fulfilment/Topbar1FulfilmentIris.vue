<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSignIn, faHeart, faShoppingCart, faSignOut, faUser, faUserPlus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"

import { TopbarFulfilmentTypes } from "@/types/TopbarFulfilment"
import { set } from "lodash-es"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faSignIn, faHeart, faShoppingCart, faSignOut, faUser, faUserPlus)

const model = defineModel<TopbarFulfilmentTypes>()
const isLoggedIn = inject("isPreviewLoggedIn", false)

const onLogout = inject("onLogout")
const layout = inject("layout", {})
</script>

<template>
	<div></div>
	<div
		id="topbar_fulfilment_1_iris"
		class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4 hidden md:flex"
		 :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			  margin : 0,
			...getStyles(model.container?.properties, screenType)
		}">
		<div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full">
			<!-- Section: Main title -->
			<div
				v-if="
					checkVisible(model?.main_title?.visible || null, isLoggedIn) &&
					textReplaceVariables(model?.main_title?.text, layout.iris_variables)
				"
				class="text-center flex items-center"
				v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)" />
		</div>

		<div
			class="action_buttons flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">

			<!-- Button: Register -->
			<a
				v-if="checkVisible(model?.register?.visible || null, isLoggedIn) && !layout.iris_varnish?.isFetching"
				href="/app/register"
				class="buttonTopbar"
            >
				<FontAwesomeIcon icon="fal fa-user-plus" class="inline opacity-70" fixed-width aria-hidden="true" />
				{{ trans("Register") }}
			</a>


			<!-- Button: Login -->
            <a
				v-if="checkVisible(model?.login?.visible || null, isLoggedIn) && !layout.iris_varnish?.isFetching"
				href="/app/login"
				class="ml-6 mr-4 buttonTopbar"
            >
				<FontAwesomeIcon icon="fal fa-sign-in" class="inline opacity-70" fixed-width aria-hidden="true" />
				{{ trans("Login") }}
			</a>


			<!-- Button: Profile -->
            <a
				v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
				href="/app/profile"
				class="ml-6 mr-4 buttonTopbar whitespace-nowrap"
            >
				<FontAwesomeIcon icon="fal fa-user" class="inline opacity-70" fixed-width aria-hidden="true" />
				<span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
			</a>


			<!-- Button: Logout -->
            <a
				v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
				href="/app/logout"
				class="ml-6 mr-4 buttonTopbar hover:!text-red-500 whitespace-nowrap"
            >
				<FontAwesomeIcon icon="fal fa-sign-out" class="inline opacity-70" fixed-width aria-hidden="true" />
				<span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
			</a>

		</div>
	</div>
</template>

<style lang="scss" scoped>

.buttonTopbar {
	@apply font-normal hover:!no-underline hover:text-yellow-500 flex items-center gap-x-1.5 py-1
}
</style>
