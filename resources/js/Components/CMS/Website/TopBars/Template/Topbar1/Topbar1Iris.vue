<script setup lang="ts">
import { inject, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from "@fal"
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons'
import { faLaptopCode } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import SwitchLanguage from "@/Components/Iris/SwitchLanguage.vue"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { set } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

library.add(faLaptopCode, faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus, faEnvelopeCircleCheck)

const props = defineProps<{
    screenType: "desktop" | "mobile" | "tablet"
}>()

interface ModelTopbar1 {
    profile: {
        text: string
        visible: string
    };
    favourite: {
        text: string
        visible: string
    };
    cart: {
        text: string
        visible: string
    };
    register: {
        text: string
        visible: string
    };
    logout: {
        text: string
        visible: string
    };
    login: {
        text: string
        visible: string
    };
    greeting: {
        text: string
    };
    main_title: {
        text: string
        visible: string
    };
    container: {
        properties: {
            color: {}
            background: {}
        }
    };
}

const model = defineModel<ModelTopbar1>()
const isLoggedIn = inject("isPreviewLoggedIn", false)
const layout = inject("layout", {})

// Section: Logout
const isLoadingLogout = ref(false)
const onClickLogout = () => {
    router.post(
        '/app/logout',
        {
            
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingLogout.value = true
            },
            onSuccess: () => {
                set(layout, ['iris', 'is_logged_in'], false)
                if (typeof window !== "undefined") {
                    let storageIris = JSON.parse(localStorage.getItem('iris') || '{}')  // Get layout from localStorage
                    localStorage.setItem('iris', JSON.stringify({
                        ...storageIris,
                        is_logged_in: false
                    }))
                }
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to logout"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingLogout.value = false
            },
        }
    )
}

const buttonClass = ref(getStyles(model.value?.button?.container?.properties, props.screenType, false))
const buttonHoverClass = ref(getStyles(model.value?.button?.hover?.container?.properties, props.screenType,false))

watch(
  () => model.value?.button,
  () => {
    buttonClass.value = getStyles(model.value?.button?.container?.properties, props.screenType,false)
    buttonHoverClass.value = getStyles(model.value?.button?.hover?.container?.properties, props.screenType,false)
  },
  { deep: true }
)

</script>

<template>
    <div id="top_bar_1_iris" class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4 sticky top-0 z-50"
        :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        margin: 0,
        ...getStyles(model?.container?.properties, screenType)
    }">
        <!-- layout?.app?.webpage_layout?.container?.properties   // TODO: should exist in Retina -->

        <div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full ">
            <!-- Section: Main title -->
            <div v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
                class="text-center flex items-center"
                v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)" />
        </div>

        <div class="hidden md:flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">
            <SwitchLanguage
                v-if="layout.app.environment !== 'production' && Object.values(layout.iris.website_i18n?.language_options || {})?.length" />

            <!-- Section: My account -->
            <LinkIris href="/app/dashboard" :type="'internal'">
                <Button
                    v-if="(checkVisible(model?.profile?.visible || null, isLoggedIn) && layout.retina?.type == 'dropshipping')"
                    type="transparent"
                    v-tooltip="trans('My account')"
                    class="button"
                >
                    <template #label>
                        <span class="button"> {{ trans('My account') }}</span>
                    </template>
                </Button>
            </LinkIris>

            <!-- Section: Profile -->
            <LinkIris :href="layout.retina?.type == 'b2b' ? '/app/dashboard' : '/app/profile'" :type="'internal'">
                <Button
                    v-if="(checkVisible(model?.profile?.visible || null, isLoggedIn))"
                    v-tooltip="trans('Profile')"
                    icon="fal fa-user"
                    type="transparent"
                    class="button"
                >
                    <template #icon>
                        <FontAwesomeIcon  class="button" icon="fal fa-user" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class="button"
                            v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
                    </template>
                </Button>
            </LinkIris>

            <!-- Section: Back in stock -->
            <LinkIris href="/app/back-in-stocks" :type="'internal'">
                <Button
                    v-if="(layout.app?.environment === 'local' && checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping')"
                    v-tooltip="trans('Reminder back in stock')"
                    type="transparent"
                    class="button"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fas fa-envelope-circle-check" class="align-middle button" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class="button">
                            {{ layout.iris_variables?.back_in_stock_count }}
                        </span>
                    </template>
                </Button>
            </LinkIris>

            <!-- Section: Favourite -->
            <LinkIris href="/app/favourites" :type="'internal'">
                <Button
                    v-if="(checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping')"
                    v-tooltip="trans('Favourites')"
                    icon="fal fa-heart"
                    type="transparent"
                    class="button"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-heart" fixed-width aria-hidden="true" class="button"/>
                    </template>
                    <template #label>
                        <div class="button" v-if="model?.favourite?.text === `{{ favourites_count }}`"
                            v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
                        <div class="button" v-else-if="model?.favourite?.text === `{{ favourites_count }} favourites`">
                            {{ layout.iris_variables?.favourites_count }} {{ layout.iris_variables?.favourites_count > 1 ?
                            trans("favourites") : trans("favourite") }}
                        </div>
                    </template>
                </Button>
            </LinkIris>


            <!-- Section: Basket (cart) -->
            <LinkIris href="/app/basket" :type="'internal'">
                <Button
                    v-if="(checkVisible(model?.cart?.visible || null, isLoggedIn) && layout.retina?.type == 'b2b')"
                    v-tooltip="trans('Cart count and amount')"  
                    type="transparent"
                    class="button"
                >
                    <template #loading>
                        <span v-show="false" class="button"></span>
                    </template>
                    <template #label="{ isLoadingVisit }">
                        <span v-tooltip="trans('Number of products line')" class="button -mr-1.5"
                            v-html="textReplaceVariables(`({{ cart_count }})`, layout.iris_variables)">
                        </span>
                        <LoadingIcon v-if="isLoadingVisit" />
                        <FontAwesomeIcon v-else icon="fal fa-shopping-cart" class="button" fixed-width
                            aria-hidden="true" />
                        <span class="button" 
                            v-html="textReplaceVariables(`{{ cart_amount }}`, layout.iris_variables)">
                        </span>
                    </template>
                </Button>
            </LinkIris>

            <!-- Section: Register -->
            <LinkIris href="/app/register" :type="'internal'">
                <Button
                    v-if="(checkVisible(model?.register?.visible || null, isLoggedIn))"
                    icon="fal fa-user-plus"
                    type="transparent"
                    class="button"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-user-plus" fixed-width
                            aria-hidden="true" class="button"/>
                    </template>
                    <template #label>
                        <span class="button">
                            {{ trans("Register") }}
                        </span>
                    </template>
                </Button>
            </LinkIris>

            <!-- Section: Login -->
            <LinkIris :href="urlLoginWithRedirect()" :type="'internal'">
                <Button
                    v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                    icon="fal fa-sign-in"
                    type="transparent"
                    class="button"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-sign-in" class="button"  fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class="button">
                            {{ trans("Login") }}
                        </span>
                    </template>
                </Button>
            </LinkIris>

            <!-- Section: Logout -->
            <Button
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                @click="() => onClickLogout()"
                icon="fal fa-sign-out"
                type="transparent"
                :loading="isLoadingLogout"
                class="button" 
            >
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sign-out" fixed-width aria-hidden="true" class="button" />
                </template>
                <template #label>
                    <span class="button">
                        {{ trans("Logout") }}
                    </span>
                </template>
            </Button>
        </div>
    </div>
</template>


<style lang="scss" scoped>
.button {
  background: v-bind('buttonClass?.background || null') !important;
  color: v-bind('buttonClass?.color || null') !important;
  font-family: v-bind('buttonClass?.fontFamily || null') !important;
  font-size: v-bind('buttonClass?.fontSize || null') !important;

  &:hover {
    background: v-bind('buttonHoverClass?.background || null') !important;
    color: v-bind('buttonHoverClass?.color || null') !important;
    font-family: v-bind('buttonHoverClass?.fontFamily || null') !important;
    font-size: v-bind('buttonHoverClass?.fontSize || null') !important;
  }
}
</style>