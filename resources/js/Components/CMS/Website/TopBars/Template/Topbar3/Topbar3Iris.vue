<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, onMounted } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables } from '@/Composables/Workshop'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import SwitchLanguage from '@/Components/Iris/SwitchLanguage.vue'

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

interface ModelTopbar1 {
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
            color: {

            }
            background: {

            }
        }
    }
}

const model = defineModel<ModelTopbar1>()

const isLoggedIn = inject('isPreviewLoggedIn', false)

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})

const onLogout = inject('onLogout')
const emits = defineEmits<{
    (e: 'setPanelActive', value: string | number): void
}>()


// Method: generate url for Login
const urlLoginWithRedirect = () => {
    if (layout.currentRoute !== "retina.login.show" && layout.currentRoute !== "retina.register") {
        return `/app/login?ref=${encodeURIComponent(window?.location.pathname)}${
            window?.location.search ? encodeURIComponent(window?.location.search) : ""
        }`
    } else {
        return "/app/login"
    }
}

</script>

<template>
    <div></div>
    <div id="top_bar_3_iris" class="py-2 px-4 justify-between flex flex-col md:flex-row"
        :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			  margin : 0,
			...getStyles(model.container?.properties, screenType)
		}"
    >

        <!-- 1: Profile, Logout -->
        <div class="flex gap-x-2 flex-wrap justify-between items-center md:justify-normal">
            <!-- Section: Profile -->
            <!-- <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                :href="model?.profile?.link.href"
                :target="model?.profile?.link.target"
                class="space-x-1.5 "
                :style="getStyles(model?.profile.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width
                    aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)"></span>
            </a> -->
            <ButtonWithLink
                v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                v-tooltip="trans('Profile')"
                url="/app/profile"
                icon="fal fa-user"
                type="transparent"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            
            <ButtonWithLink
                v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                :url="urlLoginWithRedirect()"
                icon="fal fa-sign-in"
                type="tertiary"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <SwitchLanguage
                class="md:hidden"
            />

            <!-- Section: LogoutRetina -->
            <!-- <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                 @click="()=>onLogout(model?.logout?.link)"
                class="space-x-1.5 "
                :style="getStyles(model?.logout.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width
                    aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.logout?.text, iris_variables)" />
            </a> -->
            <ButtonWithLink
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                url="/app/logout"
                method="post"
                :data="{}"
                icon="fal fa-sign-out"
                :label="trans('Logout')"
                type="negative"
            >
                <!-- <template #label>
                    <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
                </template> -->
            </ButtonWithLink>

            <!-- Login -->
            <!-- <span class="">
                <a v-if="checkVisible(model?.login.visible || null, isLoggedIn)"
                    :href="model?.login?.link.href"
                    :target="model?.login?.link.target"
                    class="space-x-1.5 cursor-pointer"
                    id=""

                    :style="getStyles(model?.login.container.properties)"

                >
                    <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                </a>
            </span> -->


            <ButtonWithLink
                v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
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
            

        </div>
        
        <!-- 2: Main text -->
        <div
            v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
            class="text-center flex items-center"
            v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
        />

        <div class="action_buttons" style="display: flex; justify-content: flex-end; column-gap: 5px; grid-column: span 5 / span 5">
            <SwitchLanguage
                class="hidden md:block"
            />

            <ButtonWithLink
                v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping'"
                v-tooltip="trans('Favourites')"
                url="/app/favourites"
                icon="fal fa-heart"
                type="transparent"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <!-- Section: Cart -->
            <!-- <a v-if="checkVisible(model?.cart?.visible || null, isLoggedIn)"
                id="header_order_totals"
                :href="model?.cart?.link.href"
                :target="model?.cart?.link.target"
                class="space-x-1.5 "
                :style="getStyles(model?.cart.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-shopping-cart' class='text-base px-[5px]' v-tooltip="trans('Cart')"
                    fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)"></span>
            </a> -->
            <ButtonWithLink
                v-if="checkVisible(model?.cart?.visible || null, isLoggedIn) && layout.retina?.type == 'b2b'"
                url="/app/basket"
                icon="fal fa-shopping-cart"
                type="transparent"
            >
                <template #label>
                    <!-- <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)" /> -->
                    <span
                        xclass="text-white"
                        xv-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)"
                        v-html="textReplaceVariables('{{ items_count }} items ({{ cart_amount }})', layout.iris_variables)"
                    >
                    </span>
                </template>
            </ButtonWithLink>



        </div>
    </div>

    <!-- <pre>{{model?.register}}</pre>

    ========== -->

</template>
