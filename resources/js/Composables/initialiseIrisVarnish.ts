/**
 * Author: Vika Aqordi
 * Created on 13-10-2025-09h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import axios from "axios"
import { set } from "lodash-es"
import Cookies from 'js-cookie'
import { onMounted } from "vue"


export const initialiseIrisVarnish = async (layoutStore) => {
        const layout = layoutStore()
        let storageIris = {}
        if (typeof window !== "undefined") {
            storageIris = JSON.parse(localStorage.getItem('iris') || '{}')  // Get layout from localStorage
            layout.iris.is_logged_in = storageIris?.is_logged_in ?? false
        }
    
        const isAppRoute = window.location.pathname.startsWith('/app')
        const selectedUrl = !isAppRoute ? '/json/first-hit' : '/app/json/first-hit'
        const currentUrl = new URL(window.location.href)
        const headers = {
            'X-Traffic-Sources': currentUrl.search?.replace(/^\?/, '') || '',
            'X-Original-Referer': currentUrl.origin + currentUrl.pathname,
            'X-Requested-With': 'XMLHttpRequest',
        }
    
        // Fetch: auth_data (GetIrisFirstHitData)
        const getVarnishData = async () => {
            try {
                set(layout, ['iris_varnish', 'isFetching'], true)
                const response = await axios.get(selectedUrl,{ headers })
                set(layout, ['iris_varnish', 'isFetching'], false)
    
                // console.log('Iris Varnish', response.data)
                return response.data
            } catch (error) {
                if (error?.status === 403) {
                    localStorage.setItem('iris', JSON.stringify({
                        ...storageIris,
                        is_logged_in: false,
                        iris_variables : null,
                        offer_meters: null
                    }))
                    layout.iris.is_logged_in = false
                }
                // console.error('Error fetching first hit:', error)
            } finally {
                set(layout, ['iris_varnish', 'isFetching'], false)
            }
        }
    
        const varnish = await getVarnishData()
        if (!varnish) return

        console.log('Initial Varnish Response:', varnish)

        // --- Handle Not Logged In ---
        if (!varnish.is_logged_in) {
            localStorage.setItem('iris', JSON.stringify({
                ...storageIris,
                is_logged_in: false,
                iris_variables: varnish?.variables ?? null,
                offer_meters: varnish?.offer_meters ?? null,
            }))

            layout.user = varnish.auth?.user || null
            if (layout.user?.customerSalesChannels) {
                layout.user.customerSalesChannels = null
            }

            layout.iris.is_logged_in = false
            layout.iris.customer = null
            return
        }

        // --- Handle Logged In ---
        if (varnish.is_logged_in) {
            
            if (varnish?.variables) {
                layout.iris_variables = varnish.variables
            }

            if (varnish?.offer_meters) {
                layout.offer_meters = varnish.offer_meters
            }

            localStorage.setItem('iris', JSON.stringify({
                ...storageIris,
                is_logged_in: true,
                iris_variables: varnish.variables,
                offer_meters: varnish.offer_meters ?? null,
            }))

            layout.user = varnish.auth?.user || null
            if (varnish.auth?.customerSalesChannels) {
                layout.user.customerSalesChannels = varnish.auth.customerSalesChannels
            }

            layout.iris.is_logged_in = true
            layout.iris.customer = varnish.customer ?? null
        }

        // --- Set Traffic Source Cookies ---
        if (varnish?.traffic_source_cookies) {
            for (const [key, cookieData] of Object.entries(varnish.traffic_source_cookies)) {
                if (cookieData?.value) {
                    Cookies.set(key, cookieData.value, cookieData.duration)
                }
            }
        }
}


export const initialiseIrisVarnishCustomerData = async (layout) => {
        const isAppRoute = window.location.pathname.startsWith('/app')
        const selectedUrl = !isAppRoute ? '/json/ecom-customer-data' : '/json/ecom-customer-data'
        const currentUrl = new URL(window.location.href)
        const headers = {
            'X-Traffic-Sources': currentUrl.search?.replace(/^\?/, '') || '',
            'X-Original-Referer': currentUrl.origin + currentUrl.pathname,
            'X-Requested-With': 'XMLHttpRequest',
        }
    
        // Fetch: auth_data (GetIrisFirstHitData)
        const getVarnishData = async () => {
            try {
                const response = await axios.get(selectedUrl,{headers})
                set(layout, ['iris_varnish', 'isFetching'], false)
    
                return response.data
            } catch (error) {
                // console.error('Error fetching auth_data:', error)
            } finally {
                set(layout, ['iris_varnish', 'isFetching'], false)
            }
        }
    
        const varnish = await getVarnishData()

        console.log('Customer Data',varnish)
    
        if (!varnish) {
            return
        }

        if (varnish?.offer_meters) {
            layout.offer_meters = varnish.offer_meters
        }
    
        if (varnish?.variables) {
            layout.iris_variables = {...layout.iris_variable,...varnish?.variables}
        }
}


export const initialiseLogUser = async (layout) => {
        const isAppRoute = window.location.pathname.startsWith('/app')
        const selectedUrl = !isAppRoute ? '/json/hit' : '/app/json/hit'
        const currentUrl = new URL(window.location.href)
        const headers = {
            'X-Traffic-Sources': currentUrl.search?.replace(/^\?/, '') || '',
            'X-Original-Referer': currentUrl.origin + currentUrl.pathname,
            'X-Requested-With': 'XMLHttpRequest',
        }
        
    
        const getLogUser = async () => {
            try {
                const response = await axios.get(selectedUrl,{headers})
    
                return response.data
            } catch (error) {
                // console.error('Error fetching web user:', error)
            } finally {}
        }
    
        const logDataUser = await getLogUser()

        console.log('logDataUser',logDataUser)
}

