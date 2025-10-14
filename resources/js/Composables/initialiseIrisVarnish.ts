/**
 * Author: Vika Aqordi
 * Created on 13-10-2025-09h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import axios from "axios"
import { set } from "lodash-es"


export const initialiseIrisVarnish = async (layoutStore) => {
    const layout = layoutStore()
    let storageIris = JSON.parse(localStorage.getItem('iris') || '{}')  // Get layout from localStorage
    console.log('storageIris', storageIris)

    layout.iris.is_logged_in = storageIris?.is_logged_in ?? false

    const selectedRoute = route().has('iris.json.auth_data') ? 'iris.json.auth_data' : 'retina.json.auth_data'

    // Fetch: auth_data (GetIrisFirstHitData)
    const getVarnishData = async () => {
        try {
            set(layout, ['iris_varnish', 'isFetching'], true)
            const response = await axios.get(route(selectedRoute))
            set(layout, ['iris_varnish', 'isFetching'], false)

            console.log('getIrisVarnish', selectedRoute, response.data)
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
        layout.iris_variables = varnish?.variables
    }

    localStorage.setItem('iris', JSON.stringify({
        ...storageIris,
        is_logged_in: varnish?.is_logged_in
    }))

    layout.user = varnish.auth.user
    if(varnish.auth?.customerSalesChannels) {
        layout.user.customerSalesChannels = varnish.auth?.customerSalesChannels
    }
    
    layout.iris.is_logged_in = varnish?.is_logged_in
    layout.iris.customer = varnish?.customer


}


export const initialiseIrisVarnishCustomerData = async (layoutStore) => {
    const layout = layoutStore()
    // let storageIris = JSON.parse(localStorage.getItem('iris') || '{}')  // Get layout from localStorage
    // console.log('storageIris', storageIris)

    const selectedRoute = route().has('iris.json.ecom_customer_data') ? 'iris.json.ecom_customer_data' : 'retina.json.ecom_customer_data'

    // Fetch: auth_data (GetIrisFirstHitData)
    const getVarnishData = async () => {
        try {
            /* set(layout, ['iris_varnish', 'isFetching'], true) */
            const response = await axios.get(route(selectedRoute))
            set(layout, ['iris_varnish', 'isFetching'], false)

            /* console.log('getIrisVarnish', selectedRoute, response.data) */
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

    


}
