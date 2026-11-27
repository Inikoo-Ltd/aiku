export const useChatThemes: Record<string, {
    name: string
    bg: string
    bgAlt: string
    line: string
    muted: string
    label: string
    text: string
    accent: string
    green: string
    cyan: string
    red: string
    yellow: string
    dark?: boolean
    rail?: string
}> = {
    classic: {
        name: 'Classic',
        bg: '#3f0e40',
        bgAlt: '#350d36',
        line: '#522653',
        muted: '#c9b8ca',
        label: '#d8c8d9',
        text: '#ffffff',
        accent: '#ffffff',
        green: '#50fa7b',
        cyan: '#8be9fd',
        red: '#ff5555',
        yellow: '#f1fa8c',
        rail: 'dracula',
    },
    light: {
        name: 'Light',
        bg: '#ffffff',
        bgAlt: '#f3f4f6',
        line: '#e5e7eb',
        muted: '#6b7280',
        label: '#9ca3af',
        text: '#111827',
        accent: '#4f46e5',
        green: '#16a34a',
        cyan: '#0891b2',
        red: '#dc2626',
        yellow: '#ca8a04',
    },
    sky: {
        name: 'Sky',
        bg: '#eef6fb',
        bgAlt: '#e0eef7',
        line: '#cfe0ec',
        muted: '#5f7d94',
        label: '#7f9bb0',
        text: '#15324a',
        accent: '#2563eb',
        green: '#15803d',
        cyan: '#0e7490',
        red: '#dc2626',
        yellow: '#b45309',
    },
    blush: {
        name: 'Blush',
        bg: '#fdf2f6',
        bgAlt: '#fae4ec',
        line: '#f3d0dd',
        muted: '#9a6a7c',
        label: '#b88a9b',
        text: '#4a1f30',
        accent: '#db2777',
        green: '#15803d',
        cyan: '#0e7490',
        red: '#b91c1c',
        yellow: '#b45309',
    },
    sand: {
        name: 'Sand',
        bg: '#faf6ee',
        bgAlt: '#f2ebdc',
        line: '#e6dcc6',
        muted: '#8a7a5e',
        label: '#a89878',
        text: '#3f3322',
        accent: '#b45309',
        green: '#3f6212',
        cyan: '#0f766e',
        red: '#b91c1c',
        yellow: '#a16207',
    },
    mint: {
        name: 'Mint',
        bg: '#effaf4',
        bgAlt: '#e1f4ea',
        line: '#cfe8da',
        muted: '#5f8a73',
        label: '#7fa892',
        text: '#14352a',
        accent: '#059669',
        green: '#15803d',
        cyan: '#0e7490',
        red: '#b91c1c',
        yellow: '#a16207',
    },
    dracula: {
        dark: true,
        name: 'Dracula',
        bg: '#282a36',
        bgAlt: '#21222c',
        line: '#44475a',
        muted: '#6272a4',
        label: '#8b91ad',
        text: '#f8f8f2',
        accent: '#bd93f9',
        green: '#50fa7b',
        cyan: '#8be9fd',
        red: '#ff5555',
        yellow: '#f1fa8c',
    },
    nord: {
        dark: true,
        name: 'Nord',
        bg: '#2e3440',
        bgAlt: '#3b4252',
        line: '#434c5e',
        muted: '#81a1c1',
        label: '#aeb8cf',
        text: '#eceff4',
        accent: '#88c0d0',
        green: '#a3be8c',
        cyan: '#8fbcbb',
        red: '#bf616a',
        yellow: '#ebcb8b',
    },
    gruvbox: {
        dark: true,
        name: 'Gruvbox',
        bg: '#282828',
        bgAlt: '#1d2021',
        line: '#3c3836',
        muted: '#928374',
        label: '#bdae93',
        text: '#ebdbb2',
        accent: '#d3869b',
        green: '#b8bb26',
        cyan: '#8ec07c',
        red: '#fb4934',
        yellow: '#fabd2f',
    },
    monokai: {
        dark: true,
        name: 'Monokai',
        bg: '#272822',
        bgAlt: '#1e1f1c',
        line: '#3e3d32',
        muted: '#75715e',
        label: '#a6a28c',
        text: '#f8f8f2',
        accent: '#ae81ff',
        green: '#a6e22e',
        cyan: '#66d9ef',
        red: '#f92672',
        yellow: '#e6db74',
    },
    onedark: {
        dark: true,
        name: 'One Dark',
        bg: '#282c34',
        bgAlt: '#21252b',
        line: '#3e4451',
        muted: '#5c6370',
        label: '#9da5b4',
        text: '#abb2bf',
        accent: '#c678dd',
        green: '#98c379',
        cyan: '#56b6c2',
        red: '#e06c75',
        yellow: '#e5c07b',
    },
    solarized: {
        dark: true,
        name: 'Solarized',
        bg: '#002b36',
        bgAlt: '#00212b',
        line: '#073642',
        muted: '#586e75',
        label: '#93a1a1',
        text: '#eee8d5',
        accent: '#6c71c4',
        green: '#859900',
        cyan: '#2aa198',
        red: '#dc322f',
        yellow: '#b58900',
    },
}

const mixColours = (from: string, to: string, amount: number) => {
    const channels = (hex: string) => [1, 3, 5].map((start) => parseInt(hex.slice(start, start + 2), 16))
    const [fromChannels, toChannels] = [channels(from), channels(to)]

    return '#' + fromChannels.map((value, index) => Math.round(value + (toChannels[index] - value) * amount).toString(16).padStart(2, '0')).join('')
}

const verbaVariables = (theme: typeof useChatThemes[string]): Record<string, string> => {
    const sidebar = theme.dark ? theme.bgAlt : mixColours(theme.bgAlt, theme.text, 0.04)
    const header = theme.dark
        ? {
            bg: mixColours(mixColours('#ffffff', theme.bg, 0.06), theme.accent, 0.06),
            text: mixColours(theme.bg, '#000000', 0.25),
            muted: mixColours(theme.muted, '#000000', 0.2),
            line: mixColours(mixColours('#ffffff', theme.bg, 0.12), theme.accent, 0.1),
            accent: mixColours(theme.accent, '#000000', 0.25),
        }
        : { bg: theme.bg, text: theme.text, muted: theme.muted, line: theme.line, accent: theme.accent }

    return {
        '--vx-sidebar': sidebar,
        '--vx-soft': mixColours('#ffffff', sidebar, theme.dark ? 0.05 : 0.4),
        '--vx-sidebar-hover': mixColours(sidebar, theme.text, theme.dark ? 0.08 : 0.06),
        '--vx-sidebar-chip': mixColours(sidebar, theme.text, 0.08),
        '--vx-sidebar-chip-strong': mixColours(sidebar, theme.text, 0.16),
        '--vx-sidebar-text': theme.dark ? theme.label : theme.muted,
        '--vx-sidebar-strong': theme.text,
        '--vx-sidebar-active': theme.accent,
        '--vx-sidebar-active-text': theme.dark ? theme.bgAlt : '#ffffff',
        '--vx-sidebar-border': theme.line,
        '--vx-sidebar-badge': theme.red,
        '--vx-head-bg': header.bg,
        '--vx-head-text': header.text,
        '--vx-head-muted': header.muted,
        '--vx-head-line': header.line,
        '--vx-head-accent': header.accent,
    }
}

const verbaVariableNames = Object.keys(verbaVariables(useChatThemes.light))

export const applyChatTheme = (key?: string) => {
    const theme = useChatThemes[key ?? ''] ?? useChatThemes.classic
    const railTheme = theme.rail ? useChatThemes[theme.rail] : theme
    const root = document.documentElement
    root.style.setProperty('--chat-bg', railTheme.bg)
    root.style.setProperty('--chat-bg-alt', railTheme.bgAlt)
    root.style.setProperty('--chat-line', railTheme.line)
    root.style.setProperty('--chat-muted', railTheme.muted)
    root.style.setProperty('--chat-label', railTheme.label)
    root.style.setProperty('--chat-text', railTheme.text)
    root.style.setProperty('--chat-accent', railTheme.accent)
    root.style.setProperty('--chat-green', railTheme.green)
    root.style.setProperty('--chat-cyan', railTheme.cyan)
    root.style.setProperty('--chat-red', railTheme.red)
    root.style.setProperty('--chat-yellow', railTheme.yellow)

    verbaVariableNames.forEach((name) => root.style.removeProperty(name))
    if (!theme.rail) {
        Object.entries(verbaVariables(theme)).forEach(([name, value]) => root.style.setProperty(name, value))
    }
}
