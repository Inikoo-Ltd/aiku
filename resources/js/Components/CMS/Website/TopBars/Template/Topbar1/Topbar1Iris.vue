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
import GoldReward from "@/Components/Utils/GoldReward.vue"

library.add(faLaptopCode, faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus, faEnvelopeCircleCheck)

const props = defineProps<{
    screenType?: "desktop" | "mobile" | "tablet"
}>()

const screenTypeInject = inject("screenType", "desktop")

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

const buttonClass = ref(getStyles(model.value?.button?.container?.properties, screenTypeInject, false))
const buttonHoverClass = ref(getStyles(model.value?.button?.hover?.container?.properties, screenTypeInject,false))

watch(
  () => model.value?.button,
  () => {
    buttonClass.value = getStyles(model.value?.button?.container?.properties, screenTypeInject,false)
    buttonHoverClass.value = getStyles(model.value?.button?.hover?.container?.properties, screenTypeInject,false)
  },
  { deep: true }
)

</script>

<template>
    <div id="top_bar_1_iris" class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4 sticky top-0 z-50"
        :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenTypeInject),
        margin: 0,
        ...getStyles(model?.container?.properties, screenTypeInject)
    }">
        <!-- layout?.app?.webpage_layout?.container?.properties   // TODO: should exist in Retina -->

        <div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full ">
            <!-- Section: Main title -->
            <div v-if="layout.offer_data && isLoggedIn" class="text-center md:text-left">
                <span>
                    {{ trans("Hello") }}, <LinkIris href="/app/dashboard" :type="'internal'" class="inline-flex items-center justify-center hover:underline">
                        <span class="font-bold">{{ layout.iris_variables?.name }}</span>
                    </LinkIris>!
                </span>
                <span v-if="layout.offer_data?.type === 'gr'" class="text-yellow-500 inline-flex items-center gap-x-1">
                    {{ layout.offer_data?.label }}
                    <GoldReward>
                        <template #default>
                            <div>
                                <FontAwesomeIcon icon="fas fa-medal" class="text-yellow-500" fixed-width aria-hidden="true" />
                                <div class="ml-1 inline-block align-middle w-20 text-xxs rounded-xs h-3 mt-1.5 bg-gray-200 relative overflow-hidden mb-2">
                                    <div class="absolute  left-0   top-0 h-full transition-all duration-1000 ease-in-out"
                                        :class="true ? 'xshimmer bg-green-500' : 'bg-green-500'"
                                        :style="{
                                            width: true ? layout.offer_data?.meter?.[0]/layout.offer_data?.meter?.[1] * 100 + '%' : '100%'
                                        }"
                                    />
                                    
                                    <div class="absolute inset-0 flex items-center justify-center text-xxs font-medium text-black">
                                        {{ Number(layout.offer_data?.meter?.[0]).toFixed(0) }} / {{ Number(layout.offer_data?.meter?.[1]).toFixed(0) }} days
                                    </div>
                                    
                                </div>
                            </div>
                        </template>
                    </GoldReward>
                </span>
                <span v-if="layout.offer_data?.type === 'fob'" class="text-yellow-500">
                    {{ layout.offer_data?.label }}
                    <FontAwesomeIcon icon="fas fa-sparkles" class="" fixed-width aria-hidden="true" />
                </span>

                
            </div>
            <div v-else-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
                class="text-center flex items-center"
                v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
            />
        </div>

        <div class="hidden md:flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">
            <SwitchLanguage
                v-if="layout.app.environment !== 'production' && Object.values(layout.iris.website_i18n?.language_options || {})?.length" />

            <!-- Section: Profile -->
            <LinkIris v-if="!layout.offer_data" href="/app/dashboard" :type="'internal'" class="flex items-center justify-center">
                <Button
                    v-if="(checkVisible(model?.profile?.visible || null, isLoggedIn))"
                    
                    icon="fal fa-user"
                    type="transparent"
                    class="button min-w-max"
                >
                    <template #icon>
                        <span v-tooltip="trans('Profile')">
                            <FontAwesomeIcon icon="fal fa-user" class="button" fixed-width aria-hidden="true" />
                        </span>
                    </template>
                    <template #label>
                        <!-- <span v-tooltip="trans('Profile')" class="button" v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" /> -->
                        <span v-tooltip="trans('Profile')" class="button">{{ trans("Profile") }}</span>
                        <!-- <GoldReward v-if="layout.offer_data?.type === 'gr'" /> -->
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
                    class="button min-w-max"
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
                            v-html="textReplaceVariables(`{{ cart_amount }} <span class='opacity-70'>({{ cart_products_amount }})</span>`, layout.iris_variables)">
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
  font-style: v-bind('buttonClass?.fontStyle || null') !important;;

  &:hover {
    background: v-bind('buttonHoverClass?.background || null') !important;
    color: v-bind('buttonHoverClass?.color || null') !important;
    font-family: v-bind('buttonHoverClass?.fontFamily || null') !important;
    font-size: v-bind('buttonHoverClass?.fontSize || null') !important;
    font-style: v-bind('buttonHoverClass?.fontStyle || null') !important;;
  }
}
</style>