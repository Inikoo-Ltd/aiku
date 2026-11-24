export const STAFF_MESSAGE_MAX_CHARACTERS = 5000

export const STAFF_MESSAGE_COUNT_SHOWN_FROM = 4000

export const staffMessageCharacters = (text: string): number => Array.from(text.trim()).length
