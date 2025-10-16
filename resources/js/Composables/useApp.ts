/**
 * Author: Vika Aqordi
 * Created on 16-10-2025-10h-21m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/


export const setColorStyleRoot = (themeColors: string[]) => {
    if (!themeColors.length) {
        return
    }

    const root = document.documentElement
    if (root) {        
        root.style.setProperty('--theme-color-0', themeColors?.[0])  // var(--theme-color-0)
        root.style.setProperty('--theme-color-1', themeColors?.[1])
        root.style.setProperty('--theme-color-2', themeColors?.[2])
        root.style.setProperty('--theme-color-3', themeColors?.[3])
        root.style.setProperty('--theme-color-4', themeColors?.[4])
        root.style.setProperty('--theme-color-5', themeColors?.[5])
    }
}