/*
 * Author Louis Perez
 * Created on 26-11-2026-16h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { ref } from "vue"

const SIDEBAR_KEY = "desk.sidebar-collapsed"

const readCollapsed = (): boolean => {
    try {
        return localStorage.getItem(SIDEBAR_KEY) === "1"
    } catch {
        return false
    }
}

const isSidebarCollapsed = ref(readCollapsed())

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value
    localStorage.setItem(SIDEBAR_KEY, isSidebarCollapsed.value ? "1" : "0")
}

const isJumpOpen = ref(false)

export const useDeskSidebar = () => ({ isSidebarCollapsed, toggleSidebar, isJumpOpen })
