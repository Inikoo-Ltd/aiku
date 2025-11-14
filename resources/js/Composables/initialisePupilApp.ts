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
// import { useEchoRetinaPersonal } from "@/Stores/echo-retina-personal.js"
// import { useEchoRetinaWebsite } from "@/Stores/echo-retina-website.js"
// import { useEchoRetinaCustomer } from "@/Stores/echo-retina-customer.js"


export const initialisePupilApp = () => {
    const layout = useLayoutStore()
    const locale = useLocaleStore()

    // const echoPersonal = useEchoRetinaPersonal()
    // const echoWebsite = useEchoRetinaWebsite()
    // const echoCustomer = useEchoRetinaCustomer()

    // layout.liveUsers = usePage().props.liveUsers || null


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

        // Set data of Website
        // if (usePage().props.layout?.website) {
        //     layout.website = usePage().props.layout?.website
        // }

        // Set data of Locale (Language)
        // if (usePage().props.layout?.customer) {
        //     layout.customer = usePage().props.layout.customer
        // }

        if (usePage().props.app) {
            layout.app = usePage().props.app
        }
        layout.app.name = "pupil"

        // Set App Environment
        if (usePage().props?.environment) {
            layout.app.environment = usePage().props?.environment
        }

        // layout.webUser_count = usePage().props.auth?.webUser_count || null

        // let moduleName = (layout.currentRoute || "").split(".")
        // layout.currentModule = moduleName.length > 1 ? moduleName[1] : ""

        if (usePage().props.auth?.user) {
            layout.user = usePage().props.auth.user
        }

        // if (usePage().props.iris) {
        //     layout.iris = usePage().props.iris
        // }

        // if (usePage().props.auth?.user?.avatar_thumbnail) {
        //     layout.avatar_thumbnail = usePage().props.auth.user.avatar_thumbnail
        // }

    })

    return layout
}
