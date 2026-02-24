/**
 * Author: Vika Aqordi
 * Created on 24-02-2026-09h-07m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/
import { trans } from 'laravel-vue-i18n'

// Method: Custom Translation function that falls back to the original text if translation is not found
export const ctrans = (text?: string) => {  
    if (!text) return ''
    return trans(text) || text
}