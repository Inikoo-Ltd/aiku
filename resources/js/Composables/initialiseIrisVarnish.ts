/**
 * Author: Vika Aqordi
 * Created on 13-10-2025-09h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { useIrisLayoutStore } from "@/Stores/irisLayout"
import { router, usePage } from "@inertiajs/vue3"
import { loadLanguageAsync } from "laravel-vue-i18n"
import { watchEffect } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import axios from "axios"
import { onBeforeMount } from "vue"
import { set } from "lodash"



export const initialiseIrisVarnish = () => {
    const layout = useIrisLayoutStore()

    const getVarnishData = async () => {
        set(layout, ['iris_varnish', 'isFetching'], true)
        const response = await axios.get(route('iris.json.auth_data'))
        set(layout, ['iris_varnish', 'isFetching'], false)

        console.log('getIrisVarnish iris.json.auth_data', response.data)
        return response.data
    }

    onBeforeMount(async () => {
        const aaa = await getVarnishData()
        if (aaa?.variables) {
            layout.iris_variables = aaa?.variables
        }
        layout.iris.is_logged_in = aaa.is_logged_in
    })

    console.log('Init Iris: ', usePage().props)

    router.on('navigate', (event) => {
        
    })

}
