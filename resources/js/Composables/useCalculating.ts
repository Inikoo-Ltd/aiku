/**
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 08-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

// From Group: Dashboard

import { get } from 'lodash-es'

// Method: to check the data is increase of decrease based on last year data
export const isUpOrDown = (orgData: {}, keyName: string | null): string => {
    const currentNumber = parseFloat(get(orgData, ['sales', `org_amount_${keyName}`], 0))
    const lastyearNumber = parseFloat(get(orgData, ['sales', `org_amount_${keyName}_ly`], 0))
    
    if (!currentNumber) return 'nodata'
    
    else if (lastyearNumber > currentNumber) {
        return 'decreased'
    } else if (lastyearNumber < currentNumber) {
        return 'increased'
    } else {
        return 'same'
    }
}


// Method: to retrive the percentage based on last year data
const calcPercentage = (orgData: {}, keyName: string | null) => {
    const currentNumber = parseFloat(get(orgData, ['sales', `org_amount_${keyName}`], 0))
    const lastyearNumber = parseFloat(get(orgData, ['sales', `org_amount_${keyName}_ly`], 0))

    // console.log(currentNumber, lastyearNumber)

    if (!currentNumber && lastyearNumber) {
        return -100 // Percentage change is infinite if currentNumber is 0
    } else if (currentNumber && !lastyearNumber) {
        return 100
    } else if (!currentNumber && !lastyearNumber) {
        return 0
    }

    return ((lastyearNumber - currentNumber) / currentNumber) * 100
}