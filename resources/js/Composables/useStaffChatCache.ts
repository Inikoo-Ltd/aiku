/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

import axios from "axios"
import type { StaffMessage } from "@/Stores/staff-messaging"

const CACHE_PREFIX = "staff-chat-cache:"
const MAX_MESSAGES_PER_CHAT = 50
const MAX_CHATS = 20
const LIFETIME_MS = 7 * 24 * 60 * 60 * 1000

type EncryptedChat = { saved_at: number; iv: string; data: string }
type CacheIndexEntry = { ulid: string; saved_at: number }

const chatKey = (userId: number, ulid: string) => `${CACHE_PREFIX}${userId}:${ulid}`
const indexKey = (userId: number) => `${CACHE_PREFIX}${userId}:index`

const toBase64 = (bytes: ArrayBuffer | Uint8Array) => btoa(String.fromCharCode(...new Uint8Array(bytes)))
const fromBase64 = (text: string) => Uint8Array.from(atob(text), (character) => character.charCodeAt(0))

let encryptionKey: Promise<CryptoKey | null> | null = null

const cacheEncryptionKey = (): Promise<CryptoKey | null> => {
    if (!globalThis.crypto?.subtle) return Promise.resolve(null)

    encryptionKey ??= axios.get(route("grp.chat.staff.cache_key"))
        .then(({ data }) => crypto.subtle.importKey("raw", fromBase64(data.key), "AES-GCM", false, ["encrypt", "decrypt"]))
        .catch(() => {
            encryptionKey = null
            return null
        })

    return encryptionKey
}

const readJson = <T>(key: string): T | null => {
    try {
        return JSON.parse(localStorage.getItem(key) ?? "null")
    } catch {
        return null
    }
}

const readIndex = (userId: number): CacheIndexEntry[] => {
    const index = readJson<CacheIndexEntry[]>(indexKey(userId))
    return Array.isArray(index) ? index : []
}

const writeIndex = (userId: number, index: CacheIndexEntry[]) => {
    try {
        localStorage.setItem(indexKey(userId), JSON.stringify(index))
    } catch { }
}

const isFresh = (savedAt: number) => Date.now() - savedAt < LIFETIME_MS

export const readCachedChat = async (userId: number, ulid: string): Promise<StaffMessage[] | null> => {
    const cached = readJson<EncryptedChat>(chatKey(userId, ulid))
    if (!cached?.iv || !cached.data || !isFresh(cached.saved_at)) return null

    const key = await cacheEncryptionKey()
    if (!key) return null

    try {
        const plain = await crypto.subtle.decrypt({ name: "AES-GCM", iv: fromBase64(cached.iv) }, key, fromBase64(cached.data))
        const messages = JSON.parse(new TextDecoder().decode(plain))
        return Array.isArray(messages) ? messages : null
    } catch {
        removeCachedChat(userId, ulid)
        return null
    }
}

export const writeCachedChat = async (userId: number, ulid: string, messages: StaffMessage[]) => {
    const key = await cacheEncryptionKey()
    if (!key) return

    const settled = messages.filter((message) => !message.client_status).slice(-MAX_MESSAGES_PER_CHAT)
    const iv = crypto.getRandomValues(new Uint8Array(12))
    const encrypted = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, new TextEncoder().encode(JSON.stringify(settled)))
    const savedAt = Date.now()

    const index = [{ ulid, saved_at: savedAt }, ...readIndex(userId).filter((entry) => entry.ulid !== ulid)]
    index.slice(MAX_CHATS).forEach((entry) => localStorage.removeItem(chatKey(userId, entry.ulid)))

    try {
        localStorage.setItem(chatKey(userId, ulid), JSON.stringify({ saved_at: savedAt, iv: toBase64(iv), data: toBase64(encrypted) }))
        writeIndex(userId, index.slice(0, MAX_CHATS))
    } catch {
        clearCachedChats()
    }
}

export const removeCachedChat = (userId: number, ulid: string) => {
    try {
        localStorage.removeItem(chatKey(userId, ulid))
        writeIndex(userId, readIndex(userId).filter((entry) => entry.ulid !== ulid))
    } catch { }
}

export const pruneCachedChats = (userId: number) => {
    const index = readIndex(userId)
    const stale = index.filter((entry) => !isFresh(entry.saved_at))
    if (!stale.length) return

    stale.forEach((entry) => localStorage.removeItem(chatKey(userId, entry.ulid)))
    writeIndex(userId, index.filter((entry) => isFresh(entry.saved_at)))
}

export const clearCachedChats = () => {
    encryptionKey = null
    try {
        Object.keys(localStorage)
            .filter((key) => key.startsWith(CACHE_PREFIX))
            .forEach((key) => localStorage.removeItem(key))
    } catch { }
}
