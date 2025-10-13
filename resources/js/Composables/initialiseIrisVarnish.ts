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



export const initialiseIrisVarnish = async () => {
    const layout = useIrisLayoutStore()
    let storageIris = JSON.parse(localStorage.getItem('iris') || '{}')  // Get layout from localStorage
    console.log('storageIris', storageIris)

    layout.iris.is_logged_in = storageIris?.is_logged_in ?? false

    const getVarnishData = async () => {
        try {
            set(layout, ['iris_varnish', 'isFetching'], true)
            const response = await axios.get(route('iris.json.auth_data'))
            set(layout, ['iris_varnish', 'isFetching'], false)

            console.log('getIrisVarnish iris.json.auth_data', response.data)
            return response.data
        } catch (error) {
            // console.error('Error fetching iris.json.auth_data:', error)
        } finally {
            set(layout, ['iris_varnish', 'isFetching'], false)
        }
    }

    // onBeforeMount(async () => {
        const aaa = await getVarnishData()
        if (aaa?.variables) {
            layout.iris_variables = aaa?.variables
        }

        localStorage.setItem('iris', JSON.stringify({
            ...storageIris,
            is_logged_in: aaa?.is_logged_in
        }))
        layout.iris.is_logged_in = aaa?.is_logged_in
    // })

    // console.log('Init Iris: ', usePage().props)

    // router.on('navigate', (event) => {
        
    // })

}
