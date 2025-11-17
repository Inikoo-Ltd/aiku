/**
 * Author: Vika Aqordi
 * Created on: 18-03-2025-10h-05m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { useLayoutStore } from "@/Stores/pupilLayout"
import { useLocaleStore } from "@/Stores/locale"
import { router, usePage } from "@inertiajs/vue3"
import { loadLanguageAsync } from "laravel-vue-i18n"
import { watchEffect } from "vue"



export const initialisePupilApp = () => {
    const layout = useLayoutStore()
    const locale = useLocaleStore()



    const storageLayout = JSON.parse(localStorage.getItem('layout') || '{}')  // Get layout from localStorage
    layout.currentPlatform = storageLayout.currentPlatform  // { 'awa' : { currentShop: 'bali', currentWarehouse: 'ed' }, ... }

    if (usePage().props?.auth?.user) {
        layout.user = usePage().props.auth.user
        // echoCustomer.subscribe(usePage().props.auth.user.customer_id)
        // Echo: Personal
        // echoPersonal.subscribe(usePage().props.auth.user.id)

        router.on('navigate', (event) => {
            // console.log('layout env', layout.app.environment)
            layout.currentParams = route().routeParams  // current params
            layout.currentRoute = route().current()  // current route

            if (layout.currentRoute?.includes('retina.dropshipping.platforms')) {
                layout.currentPlatform = layout.currentParams.platform  // 'tiktok' | 'shopify'

                localStorage.setItem('layout', JSON.stringify({
                    ...storageLayout,
                    currentPlatform: layout.currentPlatform
                }))
            }

        })
    }


    // Echo: Website wide websocket
    // echoWebsite.subscribe(usePage().props.iris.website.id)  // Websockets: notification

    if (usePage().props.localeData) {
        loadLanguageAsync(usePage().props.localeData.language.code)
    }

    watchEffect(() => {
        // Set data of Navigation
        console.log('usepage layout aaaa', usePage().props)
        if (usePage().props.layout) {
            console.log('zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz', usePage().props.layout.navigation)
            layout.navigation = usePage().props.layout.navigation || null
            // layout.secondaryNavigation = usePage().props.layout.secondaryNavigation || null
        }

        // Set data of Locale (Language)
        if (usePage().props.localeData) {
            locale.language = usePage().props.localeData.language
            locale.languageOptions = usePage().props.localeData.languageOptions
        }



        if (usePage().props.app) {
            layout.app = usePage().props.app
        }
        layout.app.name = "pupil"

        // Set App Environment
        if (usePage().props?.environment) {
            layout.app.environment = usePage().props?.environment
        }



        if (usePage().props.auth?.user) {
            layout.user = usePage().props.auth.user
        }



    })

    return layout
}
