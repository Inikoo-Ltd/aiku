import { useLayoutStore } from "@/Stores/layout"
import { useLocaleStore } from "@/Stores/locale"
import { router, usePage } from "@inertiajs/vue3"
import { loadLanguageAsync } from "laravel-vue-i18n"
import { watchEffect } from "vue"
import { useEchoGrpPersonal } from '@/Stores/echo-grp-personal.js'
import { useEchoGrpGeneral } from '@/Stores/echo-grp-general.js'
import { useLiveUsers } from '@/Stores/active-users'

export const initialiseApp = () => {
    const layout = useLayoutStore()
    const locale = useLocaleStore()

    // Declaring here cuz some component failing retrieve on first load (EmployeePosition.vue)
    layout.currentParams = route().v().params

    let storageLayout: any
    if (typeof window !== "undefined") {
        storageLayout = JSON.parse(localStorage.getItem('layout') || '{}')  // Get layout from localStorage
        layout.organisationsState = storageLayout  // { 'awa' : { currentShop: 'bali', currentWarehouse: 'ed' }, ... }
    }

    const echoPersonal = useEchoGrpPersonal()
    const echoGeneral = useEchoGrpGeneral()

    useLiveUsers().subscribe()  // Websockets: active users
    echoGeneral.subscribe(usePage().props.layout?.group?.id)  // Websockets: notification


    if (usePage().props?.auth?.user) {
        echoPersonal.subscribe(usePage().props.auth.user.id)

        router.on('navigate', (event) => {
            // console.log('layout env', layout.app.environment)
            layout.currentParams = route().v().params  // current params
            layout.currentRoute = route().current()  // current route

            const currentRouteSplit = layout.currentRoute.split('.')  // to handle grp with route grp.xxx.zzz with org with route grp.org.xxx.zzz
            layout.currentModule = currentRouteSplit[1] == 'org' ? layout.currentRoute.split('.')[2] : layout.currentRoute.split('.')[1]  // grp.org.xxx.yyy.zzz to xxx

            if (layout.currentParams?.organisation) {
                // Set current shop, current warehouse, current fulfilment
                layout.organisationsState = {
                    ...layout.organisationsState,
                    [layout.currentParams.organisation]: {
                        currentShop: route().params.shop ?? layout.organisationsState?.[layout.currentParams.organisation]?.currentShop,
                        currentWarehouse: route().params.warehouse ?? layout.organisationsState?.[layout.currentParams.organisation]?.currentWarehouse,
                        currentFulfilment: route().params.fulfilment ?? layout.organisationsState?.[layout.currentParams.organisation]?.currentFulfilment,
                        currentType: route().params.shop ? 'shop' : route().params.fulfilment ? 'fulfilment' : layout.organisationsState?.[layout.currentParams.organisation]?.currentType
                    }
                }

                if (typeof window !== "undefined") {
                    localStorage.setItem('layout', JSON.stringify({
                        ...layout.organisationsState,
                        [layout.currentParams.organisation]: {
                            currentShop: layout.organisationsState?.[layout.currentParams.organisation]?.currentShop,
                            currentWarehouse: layout.organisationsState?.[layout.currentParams.organisation]?.currentWarehouse,
                            currentFulfilment: layout.organisationsState?.[layout.currentParams.organisation]?.currentFulfilment,
                            currentType: layout.organisationsState?.[layout.currentParams.organisation]?.currentType
                        }
                    }))
                }
            }

            if (layout.currentParams?.agent) {
                // Set current shop, current warehouse, current fulfilment
                layout.agentsState = {
                    ...layout.agentsState,
                    [layout.currentParams.agent]: {
                        currentShop: route().params.shop ?? layout.agentsState?.[layout.currentParams.agent]?.currentShop,
                        currentWarehouse: route().params.warehouse ?? layout.agentsState?.[layout.currentParams.agent]?.currentWarehouse,
                        currentFulfilment: route().params.fulfilment ?? layout.agentsState?.[layout.currentParams.agent]?.currentFulfilment,
                        currentType: route().params.shop ? 'shop' : route().params.fulfilment ? 'fulfilment' : layout.organisationsState?.[layout.currentParams.agent]?.currentType
                    }
                }

                if (typeof window !== "undefined") {
                    localStorage.setItem('layout', JSON.stringify({
                        ...storageLayout,
                        [layout.currentParams.agent]: {
                            currentShop: layout.agentsState?.[layout.currentParams.agent]?.currentShop,
                            currentWarehouse: layout.agentsState?.[layout.currentParams.agent]?.currentWarehouse,
                            currentFulfilment: layout.agentsState?.[layout.currentParams.agent]?.currentFulfilment,
                            currentType: layout.agentsState?.[layout.currentParams.agent]?.currentType
                        }
                    }))
                }
            }

            const dataActiveUser = {
                ...usePage().props.auth.user,
                name: null,
                last_active: new Date(),
                action: 'navigate',
                current_page: {
                    route_name: layout.currentRoute,
                    route_params: layout.currentParams,
                    label: event.detail.page.props.title,
                    url: event.detail.page.url,
                    icon_left: usePage().props.live_users?.icon_left || null,
                    icon_right: usePage().props.live_users?.icon_right || null,
                },
            }

            // To avoid emit on logout
            if (dataActiveUser.id && layout.currentRoute !== 'grp.websites.preview' && layout.currentRoute !== 'grp.websites.webpage.preview') {
                // Set to self
                useLiveUsers().liveUsers[usePage().props.auth.user.id] = dataActiveUser

                // Websockets: broadcast to others
                window.Echo.join(`grp.live.users`).whisper('otherIsNavigating', dataActiveUser)
            }

        })
    }

    if (usePage().props.localeData) {
        loadLanguageAsync(usePage().props.localeData.language.code)
    }

    watchEffect(() => {
        // Aiku

        // Set group
        if (usePage().props.layout?.group) {
            layout.group = usePage().props.layout.group
        }


        // Set App theme
        if (usePage().props.layout?.app_theme) {
            layout.app.theme = usePage().props.layout?.app_theme
        }

        // Set App Environment
        if (usePage().props?.environment) {
            layout.app.environment = usePage().props?.environment
        }


        // Set Organisations, Agents, Digital Agency (for Multiselect in TopBar)
        if (usePage().props.layout?.organisations) {
            layout.organisations = usePage().props.layout.organisations
        }
        if (usePage().props.layout?.agents) {
            layout.agents = usePage().props.layout.agents
        }
        if (usePage().props.layout?.digital_agency) {
            layout.digital_agency = usePage().props.layout.digital_agency
            // console.log('%c digital_agency', 'background: red; color: white', usePage().props.layout.digital_agency)
        }


        // Set Navigation (for LeftSidebar)
        if (usePage().props.layout?.navigation) {
            layout.navigation = usePage().props.layout.navigation ?? null
        }


        // Set data of Locale (Language)
        if (usePage().props.localeData) {
            locale.language = usePage().props.localeData.language
            locale.languageOptions = usePage().props.localeData.languageOptions
        }


        // Set data of User (comes from app/Http/Resources/UI/LoggedUserResource.php)
        if (usePage().props.auth.user) {
            layout.user = usePage().props.auth.user
        }

        layout.app.name = "Aiku"
    })
}
