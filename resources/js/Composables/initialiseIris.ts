/**
 *  author: Vika Aqordi
 *  created on: 18-10-2024
 *  github: https://github.com/aqordeon
 *  copyright: 2024
*/

import { useIrisLayoutStore } from "@/Stores/irisLayout"
import { router, usePage } from "@inertiajs/vue3"
import { loadLanguageAsync } from "laravel-vue-i18n"
import { watchEffect } from "vue"
import { useLocaleStore } from "../Stores/locale"


export const initialiseIrisApp = () => {
    const layout = useIrisLayoutStore()
    const locale = useLocaleStore()

    console.log('Init Iris: ', usePage().props)

    router.on('navigate', (event) => {
        // To see Vue filename in console (component.vue)
        if (import.meta.env.VITE_APP_ENV === 'local' && usePage().component) {
            window.component = {
                vue: usePage().component
            }
        }

        console.log('on nav')
        layout.currentParams = route().v().params  // current params
        layout.currentQuery = route().v().query  // current query
        layout.currentRoute = route().current()  // current route
    })

    if (usePage().props?.iris?.locale) {
        loadLanguageAsync(usePage().props?.iris?.locale)
    } else if (usePage().props.localeData?.language?.code) {
        loadLanguageAsync(usePage().props.localeData?.language?.code)
    }

    watchEffect(() => {
        // Set currency to used by global
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

        if (usePage().props.iris?.variables) {
            // Will deprecated, use variables via props.iris instead
            layout.iris_variables = usePage().props.iris?.variables
        }

        if (usePage().props.iris) {
            layout.iris = usePage().props.iris
        }
        
        if (usePage().props.retina) {
            layout.retina = usePage().props.retina
        }

        if (usePage().props?.user_auth) {
            layout.user_auth = usePage().props?.user_auth
        }

        // Set data of Locale (Language)
        // if (usePage().props.localeData) {
        //     locale.language = usePage().props.localeData.language
        //     locale.languageOptions = usePage().props.localeData.languageOptions
        // }


        layout.app.name = "iris"
    })
}
