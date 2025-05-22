<script setup lang="ts">
import { trans } from "laravel-vue-i18n"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { getStyles } from "@/Composables/styles"
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop"
import Image from "@/Components/Image.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

interface ModelTopbar2 {
	greeting: {
		text: string
		visible: string
	}
	main_title: {
		text: string
		visible: string // 'all'
	}
	container: {
		properties: {
			color: {}
			background: {}
		}
	}
	logout: {
		text: string
		visible: string
		link: string
	}
	login: {}
	register: {}
	favourite: {}
	cart: {}
	profile: {}
}

const model = defineModel<ModelTopbar2>()

const isLoggedIn = inject("isPreviewLoggedIn", false)

const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", {})
const onLogout = inject("onLogout")
const isModalOpen = ref(false)

const emits = defineEmits<{
	(e: "setPanelActive", value: string | number): void
}>()
</script>

<template>
	<div></div>
	<div
		id="top_bar"
		class="hidden md:grid py-2 px-4 md:grid-cols-5"
		:style="getStyles(model?.container?.properties)">

		<div class="col-span-2 action_buttons flex justify-center md:justify-start gap-x-2">
			<!-- Section: Main title -->
			<div
				v-if="!isLoggedIn"
				class="text-center flex items-center"
				v-html="textReplaceVariables(model.main_title.text, layout.iris_variables)" />

			<!-- Section: Profile -->
			<!--  <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                :href="'/app/profile'"
                :target="model?.profile?.link?.target"
                class="hidden space-x-1.5 md:flex flex-nowrap items-center"
                :style="getStyles(model?.profile.container?.properties)"

            >
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
            </a> -->
			<ButtonWithLink
				v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
				v-tooltip="trans('Profile')"
				url="/app/profile"
				icon="fal fa-user"
				type="transparent">
				<template #label>
					<span
						v-html="
							textReplaceVariables(model?.profile?.text, layout.iris_variables)
						" />
				</template>
			</ButtonWithLink>
		</div>

		<div
			class="row-start-1 md:row-start-auto grid grid-cols-5 justify-between md:flex md:justify-center items-center">
			<ButtonWithLink
                v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                v-tooltip="trans('Profile')"
                url="/app/profile"
                icon="fal fa-user"
                class="col-span-2 md:hidden space-x-1.5 flex flex-nowrap items-center "
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>
            <Image
				class="h-9 max-w-32"
				:src="model?.logo?.source"
				imageCover />

            <ButtonWithLink
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                url="/app/logout"
                method="post"
                :data="{}"
                icon="fal fa-sign-out"
                class="col-span-2 text-right block md:hidden space-x-1.5 "
            >
                <template #label>
                <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>
		</div>

		  <div class="col-span-2 flex md:justify-end gap-x-4 ">
            <!-- Section: LogoutRetina -->
            <!-- <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                :href="model?.logout?.link"
                class="hidden md:block space-x-1.5 "
                :style="getStyles(model?.logout.container?.properties)"

            >
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width aria-hidden='true' />
                <span class="hidden md:inline" v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
            </a> -->
            <ButtonWithLink
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                url="/app/logout"
                method="post"
                :data="{}"
                icon="fal fa-sign-out"
                class="hidden md:block space-x-1.5 "
                type="negative"
            >
                <template #label>
                <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <!-- Register -->
             <span v-if="checkVisible(model?.register?.visible || null, isLoggedIn)" class="">
                <!-- <a v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
                    :href="model?.register?.link.href"
                    :target="model?.register?.link.target"
                    class="space-x-1.5 cursor-pointer"
                    id=""
                    :style="getStyles(model?.register.container?.properties)"


                >
                    <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.register.text, layout.iris_variables)" />
                </a> -->

                <ButtonWithLink
                    url="/app/register"
                    icon="fal fa-user-plus"
                    type="transparent"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-user-plus" class="" fixed-width aria-hidden="true" />
                    </template>

                    <template #label>
                        <span v-html="textReplaceVariables(model?.register.text, layout.iris_variables)" class="" />
                    </template>
                </ButtonWithLink>
             </span>


            <!-- Login -->
            <span v-if="checkVisible(model?.login?.visible || null, isLoggedIn)" class="">
                <!-- <a
                    v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                    :href="model?.login?.link.href"
                    :target="model?.login?.link.target"
                    class="space-x-1.5 cursor-pointer"
                    id=""
                    :style="getStyles(model?.login?.container?.properties)"

                >
                    <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                </a> -->

                <ButtonWithLink
                    url="/app/login"
                    icon="fal fa-sign-in"
                >
                    <template #label>
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                    </template>
                </ButtonWithLink>
            </span>

        </div>
	</div>
</template>
