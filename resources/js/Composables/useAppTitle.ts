/*
 * Author: Vika Aqordi
 * Created on: 16-11-2026
 * Github: https://github.com/aqordeon
 * Copyright: 2026
 */

import { ref } from "vue"

const defaultTitleSuffix = "aiku"

export const appTitleSuffix = ref(defaultTitleSuffix)

export const formatAppTitle = (title: string): string => (title ? `${title} - ${appTitleSuffix.value}` : appTitleSuffix.value)

export const useAppTitleSuffix = (suffix: string): void => {
	appTitleSuffix.value = suffix
}

export const resetAppTitleSuffix = (): void => {
	appTitleSuffix.value = defaultTitleSuffix
}
