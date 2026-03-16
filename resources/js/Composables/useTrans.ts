/**
 * Author: Vika Aqordi
 * Created on 24-02-2026-09h-07m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/
import { trans } from 'laravel-vue-i18n'
import { ReplacementsInterface } from 'laravel-vue-i18n/interfaces/replacements'

// Method: Custom Translation function that falls back to the original text if translation is not found
export const ctrans = (text: string, replacements: ReplacementsInterface = {}) => {  
    if (!text) return ''

    if (trans(text, replacements)) {
        return trans(text, replacements)
    } else {
        Object.keys(replacements).forEach(key => {
            const value = replacements[key];
            const regex = new RegExp(`:${key}`, 'g')
            text = text.replace(regex, value.toString())
        })

        return text
    }
}