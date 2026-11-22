/**
 *  author: Vika Aqordi
 *  created on: 18-10-2024
 *  github: https://github.com/aqordeon
 *  copyright: 2024
*/

import { useIrisLayoutStore } from "@/Stores/irisLayout"
import { router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { loadLanguageAsync } from "laravel-vue-i18n"
import { watchEffect } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { initialiseIrisVarnishCustomerData, recordWebsiteHit } from '@/Composables/initialiseIrisVarnish'
import { setComponentDebugInfo } from '@/Composables/useComponentDebugInfo'


let isJsonLocaleInterceptorAdded = false

export const initialiseIrisApp = () => {
    const layout = useIrisLayoutStore()
    const locale = useLocaleStore()

    // console.log('Init Iris: ', usePage().props)

    setComponentDebugInfo()

    router.on('before', (event) => {
        const chosenLocale = layout.iris?.locale
        const visitUrl = event.detail.visit.url
        if (
            event.detail.visit.method === 'get'
            && chosenLocale
            && chosenLocale !== layout.iris?.website_i18n?.shop_language?.code
            && visitUrl.origin === window.location.origin
            && !visitUrl.pathname.startsWith('/app')
            && !visitUrl.searchParams.has('locale')
        ) {
            visitUrl.searchParams.set('locale', chosenLocale)
        }
    })

    if (!isJsonLocaleInterceptorAdded) {
        isJsonLocaleInterceptorAdded = true
        axios.interceptors.request.use((config) => {
            const chosenLocale = layout.iris?.locale
            if (typeof window === 'undefined' || !chosenLocale || chosenLocale === layout.iris?.website_i18n?.shop_language?.code) {
                return config
            }

            const requestUrl = new URL(config.url ?? '', window.location.origin)
            if (
                requestUrl.origin === window.location.origin
                && requestUrl.pathname.startsWith('/json')
                && !requestUrl.searchParams.has('locale')
                && !config.params?.locale
            ) {
                config.params = { ...(config.params ?? {}), locale: chosenLocale }
            }

            return config
        })
    }

    router.on('navigate', (event) => {
        setComponentDebugInfo()  // To see PHP action and Vue filename in console (window.component)

        layout.currentParams = route().routeParams  // current params
        layout.currentQuery = route().queryParams  // current query
        layout.currentRoute = route().current()  // current route
    })

    if (usePage().props?.iris?.locale) {
        loadLanguageAsync(usePage().props?.iris?.locale)
    } else if (usePage().props.localeData?.language?.code) {
        loadLanguageAsync(usePage().props.localeData?.language?.code)
    }



    watchEffect(() => {
        // Set currency to use by global
        if (usePage().props.iris?.currency) {       
            locale.currencyInertia = usePage().props.iris?.currency
        }

        // Set App theme
        if (usePage().props.layout?.app_theme) {       
            layout.app.theme = usePage().props.layout?.app_theme
        }

        // Set App Environment
        if (usePage().props?.environment) {
            layout.app.environment = usePage().props?.environment
        }

        // Set User data
        if (usePage().props?.auth?.user) {
            layout.user = usePage().props?.auth
        }

        if (usePage().props.iris) {
            layout.iris = {
                ...layout.iris,
                ...usePage().props.iris
            }
        }
        
        if (usePage().props.retina) {
            layout.retina = usePage().props.retina  // HandleIrisInertiaRequests
        }

        layout.reload_handle = () => initialiseIrisVarnishCustomerData(layout)
        layout.recordWebsiteHit = () => recordWebsiteHit()

        if (usePage().props.outboxes) {
            layout.outboxes = usePage().props.outboxes
        }


        // Set data of Locale (Language)
        // if (usePage().props.localeData) {
        //     locale.language = usePage().props.localeData.language
        //     locale.languageOptions = usePage().props.localeData.languageOptions
        // }


        layout.app.name = "iris"
    })
}
