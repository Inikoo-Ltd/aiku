/**
 * Author: Vika Aqordi
 * Created on 21-09-2026-10h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

type LocaleMessages = Record<string, string>

const langModules = import.meta.glob('../../../lang/*.json')

const messagesByLocale = new Map<string, Promise<LocaleMessages>>()

export const normalizeLocale = (locale?: string | null): string => (locale || 'en').replace('-', '_')

const langModuleFor = (locale: string) =>
    langModules[`../../../lang/${locale}.json`] ?? langModules[`../../../lang/${locale.replace('_', '-')}.json`]

export const loadLocaleMessages = (locale: string): Promise<LocaleMessages> => {
    if (!messagesByLocale.has(locale)) {
        const langModule = langModuleFor(locale)

        messagesByLocale.set(
            locale,
            langModule
                ? langModule().then((module: any) => module.default ?? {})
                : Promise.resolve({})
        )
    }

    return messagesByLocale.get(locale) as Promise<LocaleMessages>
}

export const irisI18nOptions = (locale: string, messages: LocaleMessages) => ({
    lang: locale,
    fallbackLang: locale,
    resolve: (lang: string) => (lang === locale ? messages : {}),
})
