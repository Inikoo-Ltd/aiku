/**
 * Author: Vika Aqordi
 * Created on 06-11-2025-16h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import axios from "axios"

export const getRefRedirect = async (options: { registered?: boolean } = {}) => {
    try {
        const params = new URLSearchParams()
        const ref = route()?.params?.['ref']

        if (ref) {
            params.set('ref', String(ref))
        }

        if (options.registered) {
            params.set('registered', '1')
        }

        const response = await axios.get(`/json/canonical-redirect?${params.toString()}`)

        if (response.data?.redirect_url) {
            return response.data?.redirect_url
        }

        return route('iris.iris_webpage')
    } catch (error: any) {
        return route('iris.iris_webpage')
    }
}
