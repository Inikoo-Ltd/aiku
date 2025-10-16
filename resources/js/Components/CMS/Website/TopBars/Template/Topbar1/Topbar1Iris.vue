<script setup lang="ts">
import { inject, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from "@fal"
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons'
import { faLaptopCode } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { checkVisible, textReplaceVariables } from "@/Composables/Workshop"
// import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { Skeleton } from "primevue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import SwitchLanguage from "@/Components/Iris/SwitchLanguage.vue"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { set } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

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
            <a href="/app/dashboard">
                <Button
                    v-if="(checkVisible(model?.profile?.visible || null, isLoggedIn) && layout.retina?.type == 'dropshipping') && !layout.iris_varnish?.isFetching"
                    type="transparent"
                    class="bg-transparent"
                    v-tooltip="trans('My account')"
                    url=""
                    :noHover="true"
                    :injectStyle="getStyles(model?.container?.properties, props.screenType)"
                >
                    <template #label>
                        <span class="text-white"> {{ trans('My account') }}</span>
                    </template>
                </Button>
            </a>

            <!-- Section: Profile -->
            <a :href="layout.retina?.type == 'b2b' ? '/app/dashboard' : '/app/profile'">
                <Button
                    v-if="(checkVisible(model?.profile?.visible || null, isLoggedIn) )&& !layout.iris_varnish?.isFetching"
                    v-tooltip="trans('Profile')"
                    xurl="layout.retina?.type == 'b2b' ? '/app/dashboard' : '/app/profile'"
                    icon="fal fa-user"
                    type="transparent"
                    :noHover="true"
                    :injectStyle="getStyles(model?.container?.properties, props.screenType)"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-user" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class=""
                            v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
                    </template>
                </Button>
            </a>

            <!-- Section: Back in stock -->
            <a href="/app/back-in-stocks">
                <Button
                    v-if="(layout.app?.environment === 'local' && checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping') && !layout.iris_varnish?.isFetching"
                    v-tooltip="trans('Reminder back in stock')"
                    url=""
                    type="transparent"
                    :noHover="true"
                    :injectStyle="getStyles(model?.container?.properties, props.screenType)"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fas fa-envelope-circle-check" class="align-middle" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class="">
                            {{ layout.iris_variables?.back_in_stock_count }}
                        </span>
                    </template>
                </Button>
            </a>

            <!-- Section: Favourite -->
            <a href="/app/favourites">
                <Button
                    v-if="(checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping') && !layout.iris_varnish?.isFetching"
                    v-tooltip="trans('Favourites')"
                    url=""
                    icon="fal fa-heart"
                    type="transparent"
                    :noHover="true"
                    :injectStyle="getStyles(model?.container?.properties, props.screenType)"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-heart" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class="" v-if="model?.favourite?.text === `{{ favourites_count }}`"
                            v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
                        <span class="" v-else-if="model?.favourite?.text === `{{ favourites_count }} favourites`">
                            {{ layout.iris_variables?.favourites_count }} {{ layout.iris_variables?.favourites_count > 1 ?
                            trans("favourites") : trans("favourite") }}
                        </span>
                    </template>
                </Button>
            </a>


            <!-- Section: Basket (cart) -->
            <a href="/app/basket">
                <Button
                    v-if="(checkVisible(model?.cart?.visible || null, isLoggedIn) && layout.retina?.type == 'b2b') && !layout.iris_varnish?.isFetching"
                    v-tooltip="trans('Cart count and amount')"
                    url=""
                    :noHover="true"
                    type="transparent"
                    :injectStyle="getStyles(model?.container?.properties, props.screenType)"
                >
                    <template #loading>
                        <span v-show="false" class=""></span>
                    </template>
                    <template #label="{ isLoadingVisit }">
                        <!-- <span class="text-white" xv-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)"
                            v-html="textReplaceVariables(`{{ items_count }} ${trans('items')} ({{ cart_amount }})`, layout.iris_variables)">
                        </span> -->
                        <span v-tooltip="trans('Number of products line')" class=" -mr-1.5" xv-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)"
                            v-html="textReplaceVariables(`({{ cart_count }})`, layout.iris_variables)">
                        </span>
                        <LoadingIcon v-if="isLoadingVisit" />
                        <FontAwesomeIcon v-else icon="fal fa-shopping-cart" fixed-width
                            aria-hidden="true" />
                        <span class="" xv-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)"
                            v-html="textReplaceVariables(`{{ cart_amount }}`, layout.iris_variables)">
                        </span>
                    </template>
                </Button>
            </a>

            <!-- Section: Register -->
            <a href="/app/register">
                <Button
                    v-if="(checkVisible(model?.register?.visible || null, isLoggedIn)) && !layout.iris_varnish?.isFetching"
                    xurl="/app/register"
                    icon="fal fa-user-plus"
                    type="transparent"
                    :noHover="true"
                    :injectStyle="getStyles(model?.container?.properties, props.screenType)"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-user-plus" fixed-width
                            aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class="">
                            {{ trans("Register") }}
                        </span>
                    </template>
                </Button>
            </a>

            <!-- Section: Login -->
            <a :href="urlLoginWithRedirect()">
                <Button
                    v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                    xurl="urlLoginWithRedirect()"
                    icon="fal fa-sign-in"
                    type="transparent"
                    :noHover="true"
                    :injectStyle="getStyles(model?.container?.properties, props.screenType)"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-sign-in" xstyle="{ color: 'white' }" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <span class="text-inherit" :style="getStyles(model?.container?.properties, props.screenType)">
                            {{ trans("Login") }}
                        </span>
                    </template>
                </Button>
            </a>

            <!-- Section: Logout -->
            <Button
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                @click="() => onClickLogout()"
                icon="fal fa-sign-out"
                type="transparent"
                :noHover="true"
                :loading="isLoadingLogout"
                :injectStyle="getStyles(model?.container?.properties, props.screenType)"
            >
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sign-out" fixed-width aria-hidden="true" />
                </template>
                <template #label>
                    <span class="">
                        {{ trans("Logout") }}
                    </span>
                </template>
            </Button>

            <!-- <div  v-if="layout.iris_varnish?.isFetching" class="flex flex-col md:flex-row md:justify-between gap-x-4">
                <Skeleton  width="8rem" height="2rem"
                    class="rounded-xl opacity-70 bg-white/10 animate-pulse" />
                <Skeleton  width="8rem" height="2rem"
                    class="rounded-xl opacity-70 bg-white/10 animate-pulse" />
            </div> -->
        </div>
    </div>
</template>
