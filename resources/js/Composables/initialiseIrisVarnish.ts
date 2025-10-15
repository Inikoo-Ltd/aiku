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
    onMounted(async () => {
        const layout = layoutStore()
        let storageIris = {}
        if (typeof window !== "undefined") {
            storageIris = JSON.parse(localStorage.getItem('iris') || '{}')  // Get layout from localStorage
            layout.iris.is_logged_in = storageIris?.is_logged_in ?? false
        }
    
        const selectedRoute = route().has('iris.json.first_hit') ? 'iris.json.first_hit' : 'retina.json.first_hit'
    
        // Fetch: auth_data (GetIrisFirstHitData)
        const getVarnishData = async () => {
            try {
                set(layout, ['iris_varnish', 'isFetching'], true)
                const response = await axios.get(route(selectedRoute))
                set(layout, ['iris_varnish', 'isFetching'], false)
    
                console.log('Iris Varnish', response.data)
                return response.data
            } catch (error) {
                console.error('Error fetching first hit:', error)
            } finally {
                set(layout, ['iris_varnish', 'isFetching'], false)
            }
        }
    
        const varnish = await getVarnishData()
    
        if (!varnish) {
            return
        }
    
        if (varnish?.variables) {
            layout.iris_variables = varnish?.variables
        }
    
        localStorage.setItem('iris', JSON.stringify({
            ...storageIris,
            is_logged_in: varnish?.is_logged_in
        }))
    
        layout.user = varnish.auth?.user
        if(varnish.auth?.customerSalesChannels) {
            layout.user.customerSalesChannels = varnish.auth?.customerSalesChannels
        }
        
        layout.iris.is_logged_in = varnish?.is_logged_in
        layout.iris.customer = varnish?.customer
    
        for(const item in varnish?.variables?.traffic_source_cookies){
            let data = varnish?.variables?.traffic_source_cookies[item]
            Cookies.set(item,data.value,data.duration)
        }
    })
}


export const initialiseIrisVarnishCustomerData = async (layout) => {
    onMounted(async () => {
        const selectedRoute = route().has('iris.json.ecom_customer_data') ? 'iris.json.ecom_customer_data' : 'retina.json.ecom_customer_data'
    
        // Fetch: auth_data (GetIrisFirstHitData)
        const getVarnishData = async () => {
            try {
                const response = await axios.get(route(selectedRoute))
                set(layout, ['iris_varnish', 'isFetching'], false)
    
                return response.data
            } catch (error) {
                console.error('Error fetching auth_data:', error)
            } finally {
                set(layout, ['iris_varnish', 'isFetching'], false)
            }
        }
    
        const varnish = await getVarnishData()
    
        if (!varnish) {
            return
        }
    
        if (varnish?.variables) {
            layout.iris_variables = {...layout.iris_variable,...varnish?.variables}
        }
    })
}


export const initialiseLogUser = async (layout) => {
    onMounted(async () => {
        const selectedRoute = route().has('iris.json.log_web_user_request') ? 'iris.json.log_web_user_request' : 'retina.json.log_web_user_request'
    
        const getLogUser = async () => {
            try {
                const response = await axios.get(route(selectedRoute))
    
                return response.data
            } catch (error) {
                console.error('Error fetching web user:', error)
            } finally {}
        }
    
        const logDataUser = await getLogUser()
    })
}

