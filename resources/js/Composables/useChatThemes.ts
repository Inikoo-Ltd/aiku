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
}> = {
    dracula: {
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

export const applyChatTheme = (key?: string) => {
    const theme = useChatThemes[key ?? ''] ?? useChatThemes.dracula
    const root = document.documentElement
    root.style.setProperty('--chat-bg', theme.bg)
    root.style.setProperty('--chat-bg-alt', theme.bgAlt)
    root.style.setProperty('--chat-line', theme.line)
    root.style.setProperty('--chat-muted', theme.muted)
    root.style.setProperty('--chat-label', theme.label)
    root.style.setProperty('--chat-text', theme.text)
    root.style.setProperty('--chat-accent', theme.accent)
    root.style.setProperty('--chat-green', theme.green)
    root.style.setProperty('--chat-cyan', theme.cyan)
    root.style.setProperty('--chat-red', theme.red)
    root.style.setProperty('--chat-yellow', theme.yellow)
}
