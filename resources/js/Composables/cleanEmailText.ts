/*
 * Mail imported before the parser stopped keeping stylesheets has the css itself as its text:
 * strip_tags removed the tags but kept what was between them, so every <style> block survived
 * as body text. Those rows cannot be mended without re-importing, so they are cleaned on the
 * way to the screen.
 */

const ZERO_WIDTH = /[​-‍⁠﻿]/g

const looksLikeMarkup = (value: string) => /<[a-z!/]/i.test(value) || /[^{}]\{[^{}]*:[^{}]*\}/.test(value)

const decodeEntities = (value: string) => {
    if (!value.includes("&")) {
        return value
    }

    if (typeof DOMParser === "undefined") {
        return value
    }

    return new DOMParser().parseFromString(value, "text/html").body.textContent ?? value
}

/**
 * Anything shaped like a css rule, including the nested ones inside @media. Three passes is
 * enough for the nesting real mail uses, and stops it looping on malformed input.
 */
const dropStyleRules = (value: string) => {
    let text = value

    for (let pass = 0; pass < 3 && text.includes("{"); pass++) {
        text = text
            .replace(/@[a-z-]+[^{}]*\{(?:[^{}]|\{[^{}]*\})*\}/gi, " ")
            .replace(/[^{}]*\{[^{}]*:[^{}]*\}/g, " ")
    }

    return text
}

const stripTags = (value: string) =>
    value
        .replace(/<(style|script|head|title)[\s\S]*?<\/\1>/gi, " ")
        .replace(/<!--[\s\S]*?-->/g, " ")
        .replace(/<[^>]+>/g, " ")

/**
 * Returns text, never markup. Callers must keep escaping it: this is a cleaner, not a
 * sanitiser, and the only safe way to show a stranger's email is as text.
 *
 * Decoding runs before stripping and both run twice, because "&lt;script&gt;" survives a
 * strip untouched and would come out the other side as a real tag if it were decoded after.
 */
export const cleanEmailText = (value?: string | null): string => {
    if (!value) {
        return ""
    }

    if (!looksLikeMarkup(value) && !value.includes("&")) {
        return value
    }

    let text = value

    for (let pass = 0; pass < 2; pass++) {
        text = stripTags(decodeEntities(text))
    }

    return dropStyleRules(text)
        // Bulk mail pads its preheader with zero width joiners so the preview line looks empty.
        .replace(ZERO_WIDTH, "")
        .replace(/[ \t ]+/g, " ")
        .replace(/\n{3,}/g, "\n\n")
        .trim()
}
