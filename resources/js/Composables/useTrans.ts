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

    const normalizedReplacements = Object.entries(replacements).reduce(
        (acc, [key, value]) => {
            acc[key] = value ?? ''
            return acc
        },
        {} as ReplacementsInterface
    )

    if (trans(text, normalizedReplacements)) {
        return trans(text, normalizedReplacements)
    } else {
        Object.entries(normalizedReplacements).forEach(([key, value]) => {
            const regex = new RegExp(`:${key}`, 'g')
            text = text.replace(regex, value.toString())
        })

        return text
    }
}