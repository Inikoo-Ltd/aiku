/**
 * Author: Vika Aqordi
 * Created on 06-11-2025-16h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import axios from "axios"

// Method: to get the redirect url
export const getRefRedirect = async () => {
    try {
        console.log('-Re:')

        const response = await axios.get(
          `/json/canonical-redirect?ref=${route()?.params?.['ref']}`,
        )



        console.log('-Response-redirect', response)


        if (response.data?.redirect_url) {
            return response.data?.redirect_url  // "https://ecom.test/gold_reward"
        }

        return route('retina.dashboard.show')


    } catch (error: any) {
        return route('retina.dashboard.show')
    }
}