/**
 * Author: Vika Aqordi
 * Created on: 25-06-2025-10h-50m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

export const ChannelLogo = (channel: string) => {
    const listLogo: {[key: string]: string} = {
        'ebay': ``,
        'shopify': ``,
        'tiktok': ``,
        'amazon': ``,
        'amazon_simple': ``,
        'woocommerce': ``,
        'wix': ``,
        'manual': ``,
        'magento': ``
    }

    return listLogo[channel]
}