<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Modal from '@/Components/Utils/Modal.vue'
library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

const isLoggedIn = ref(false)
const isDropshipping = ref(false)
const sectionAuth = ref<string | null>()

const locale = inject('locale', aikuLocaleStructure)

const isModalOpen = ref(false)

const onClickRegister = () => {
    isModalOpen.value = true
    sectionAuth.value = 'register'
}

const onClickLogin = () => {
    isModalOpen.value = true
    sectionAuth.value = 'login'
}
</script>

<template>
    <div id="top_bar" class="bg-[rgb(75,80,88)] text-white py-1 px-4 flex justify-between font-[Raleway]">
        <div class="flex">
            <div v-if="isLoggedIn">
                <div>Hello,</div>
                <div
                    class="font-semibold max-w-[180px] text-ellipsis inline-block whitespace-nowrap ml-[5px] overflow-hidden">
                    Katka Buchy
                </div>
            </div>

            <div id="top_bar_is_gold_reward_member" class="hide" style="margin-left: 20px;">
                <i class="fal fa-sparkles" style="color: #ffebb1;"></i>
                <div id="top_bar_is_gold_reward_member_label"
                    style="padding: 1px 2px  1px 3px;color: #ffbf00;font-weight: 600;"></div>
                <i class="fal fa-sparkles" style="color: #ffebb1;"></i>
                <div id="top_bar_is_gold_reward_member_until"
                    style="white-space: nowrap;display: inline-block;font-size: 0.7rem;margin-left: 2px;"></div>
            </div>

            <div id="top_bar_is_first_order_bonus" class="hide" style="margin-left: 20px;">
                <i class="fal fa-sparkles" style="color: #ffebb1;"></i>
                <div id="top_bar_is_first_order_bonus_label"
                    style="padding: 1px 2px  1px 3px;color: #ffbf00;font-weight: 600;"></div>
                <i class="fal fa-sparkles" style="color: #ffebb1;"></i>
            </div>
        </div>

        <div class="action_buttons" style="display: flex; justify-content: flex-end; column-gap: 45px; grid-column: span 5 / span 5">
            <template v-if="isLoggedIn">
                <a href="#" class="space-x-1.5" style="margin-left: 0;">
                    <!-- <i class="far fa-flip-horizontal fa-sign-out" title="Log out" aria-hidden="true"></i> -->
                    <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width
                        aria-hidden='true' />
                    <span>Log out</span>
                </a>
                <a id="profile_button" href="profile.sys" class="space-x-1.5">
                    <!-- <i class="far fa-user fa-flip-horizontal  " title="Profile" aria-hidden="true"></i> -->
                    <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width
                        aria-hidden='true' />
                    <span>Profile</span>
                </a>
                <a id="favorites_button" href="favourites.sys" class="mx-0 space-x-1.5">
                    <!-- <i class=" far fa-heart" title="My favourites" aria-hidden="true"></i> -->
                    <FontAwesomeIcon icon='fal fa-heart' class='' fixed-width aria-hidden='true' />
                    <span>My favourites</span>
                </a>
                <a id="header_order_totals" href="basket.sys" class="space-x-1.5" style="">
                    <span class="ordered_products_number">11</span>
                    <FontAwesomeIcon icon='fal fa-shopping-cart' class='text-base px-[5px]' v-tooltip="trans('Basket')"
                        fixed-width aria-hidden='true' />
                    <span class="order_amount" title="" style="font-weight: 600; font-size: 1.1rem;">
                        ${{ 4561237486 }}
                    </span>
                </a>
            </template>

            <template v-else>
                <template v-if="isDropshipping">
                    <a href="/login.sys" class="space-x-1.5" id="">
                        <span>Call us</span>
                    </a>
                </template>

                <template v-else>
                    <div @click="() => onClickLogin()" href="/login.sys" class="space-x-1.5" id="">
                        <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                        <span>Login</span>
                    </div>
                    <div @click="() => onClickRegister()" href="/register.sys" class="space-x-1.5">
                        <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                        <span>Register</span>
                    </div>
                </template>
            </template>

        </div>
    </div>

    <Modal :isOpen="isModalOpen" @onClose="() => isModalOpen = false">
        <div v-if="sectionAuth === 'login'" class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div class="sm:mx-auto sm:w-full sm:max-w-md">
                <h2 class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight">
                    Sign in to your account
                </h2>
            </div>

            <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px] bg-white px-6 py-12 border border-gray-100 shadow sm:rounded-lg sm:px-12">
                <form class="space-y-6" action="#" method="POST">
                    <div>
                        <label for="email" class="block text-sm font-medium leading-6">
                            Email address
                        </label>
                        <div class="mt-2">
                            <input id="email" name="email" type="email" autocomplete="email" required=""
                                class="block w-full rounded-md border-0 py-1.5 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium leading-6">Password</label>
                        <div class="mt-2">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required=""
                                class="block w-full rounded-md border-0 py-1.5 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                            <label for="remember-me" class="select-none ml-3 block text-sm leading-6">Remember me</label>
                        </div>

                        <div class="text-sm leading-6">
                            <a href="#" class="font-semibold text-indigo-600 hover:text-indigo-500">Forgot
                                password?</a>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                            class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Sign
                            in</button>
                    </div>
                </form>

                <div>
                    <div class="relative mt-10">
                        <div class="absolute inset-0 flex items-center" aria-hidden="true">
                            <div class="w-full border-t border-gray-200" />
                        </div>
                        <div class="relative flex justify-center text-sm leading-6">
                            <span class="bg-white text-gray-500 px-6">{{ trans("Don't have account?") }}</span>
                        </div>
                    </div>

                    <div class="mt-2 gap-4">


                        <div @click="() => sectionAuth = 'register'" href="#"
                            class="flex w-full items-center justify-center gap-3 rounded-md bg-white text-gray-600 px-3 py-2 text-sm font-semibold shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:ring-transparent">
                            Register
                        </div>
                    </div>
                </div>
            </div>


        </div>

        <!-- Register -->
        <div v-if="sectionAuth === 'register'" class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
            <h1 class="text-center text-2xl font-bold text-slate-800">Register</h1>
            <form class="space-y-6 mt-10 sm:mx-auto sm:w-full sm:max-w-[480px] bg-white px-6 py-12 border border-gray-100 shadow sm:rounded-lg sm:px-12">
                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700">{{ trans('Contact Name') }}</label>
                    <div class="mt-1">
                        <input ref="inputContactName" id="contact_name" name="contact_name" :autofocus="true"
                            autocomplete="contact_name" required
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                    </div>
                </div>

                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700">{{ trans('Username') }}</label>
                    <div class="mt-1">
                        <input ref="inputUsername" id="username" name="username" :autofocus="true"
                            autocomplete="username" required
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                    </div>
                </div>

                <!-- Section: Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700"> {{ trans('Password') }} </label>
                    <div class="mt-1 flex flex-col rounded-md shadow-sm">
                        <input type="password" autocomplete="off"
                            placeholder="Enter password" class="text-gray-700 placeholder-gray-400 shadow-sm focus:ring-gray-500 focus:border-gray-500 w-full border-gray-300 rounded-l-md" />
                    </div>
                </div>

                <!-- Section: Password repeat -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700"> {{ trans('Repeat password') }} </label>
                    <div class="mt-1 flex flex-col rounded-md shadow-sm">
                        <input type="password" autocomplete="off"
                            placeholder="Reenter password" class="text-gray-700 placeholder-gray-400 shadow-sm focus:ring-gray-500 focus:border-gray-500 w-full border-gray-300 rounded-l-md" />
                    </div>
                </div>

                <div>
                            <button type="submit"
                                class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                Register
                            </button>
                        </div>
                <div>
                    <div class="relative mt-10">
                        <div class="absolute inset-0 flex items-center" aria-hidden="true">
                            <div class="w-full border-t border-gray-200" />
                        </div>
                        <div class="relative flex justify-center text-sm leading-6">
                            <span class="bg-white text-gray-500 px-6">Already have an account?</span>
                        </div>
                    </div>

                    <div class="mt-2 gap-4">

                        <div @click="() => sectionAuth = 'login'" href="#"
                            class="flex w-full items-center justify-center gap-3 rounded-md bg-white text-gray-600 px-3 py-2 text-sm font-semibold shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:ring-transparent">
                            Login
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </Modal>
</template>