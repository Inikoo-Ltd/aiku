/**
 *  author: Vika Aqordi
 *  created on: 18-10-2024
 *  github: https://github.com/aqordeon
 *  copyright: 2024
*/


// Check if the user is logged in
export const checkVisible = (visible: string | null, isLoggedIn: boolean) => {
    if (!visible) return false

    if (visible === 'all') {
        return true
    } else if (visible === 'login') {
        return isLoggedIn
    } else {
        return !isLoggedIn
    }
}

// all, logout, login 
export const viewVisible = (mode = true , visibilty = 'all') =>{
    if(visibilty == 'all') return true
    else if(mode && visibilty == "logout") return false
    else if(!mode && visibilty == "login") return false
    else return true
}

export const setIframeView = (view: String) => {
    if (view == 'mobile') {
        return 'w-sm h-full mx-auto';
    } else if (view == 'tablet') {
        return 'max-w-4xl w-full h-full mx-auto';
    } else {
        return 'w-full h-full';
    }
}

// Send data to parent window
export const iframeToParent = (data: any) => {
    if (window) {
        window.parent.postMessage(data, '*')
    }
}

// Send data to parent window
export const sendMessageToParent = (key: string, value: any) => {
    const serializableValue = JSON.parse(JSON.stringify(value));
    window.parent.postMessage({ key, value: serializableValue }, '*');
}

// To show in workshop
export const dummyIrisVariables = {
    name: 'Aqordeon',
    username: 'aqordeon',
    email: 'aqordeon@email.com',
    favourites_count: 95,
    cart_count: 13,
    cart_amount: '£455.98',
    reference: '000001'
}

export const textReplaceVariables = (text?: string, piniaVariables?: {}) => {
    if (!text) {
        return ''
    }

    if (!piniaVariables) {
        return text
    }

    return text.replace(/\{\{\s*name\s*\}\}/g, piniaVariables?.name || piniaVariables?.username || '-')
    .replace(/\{\{\s*username\s*\}\}/g, piniaVariables?.username || '-')
    .replace(/\{\{\s*email\s*\}\}/g, piniaVariables?.email || '-')
    .replace(/\{\{\s*favourites_count\s*\}\}/g, piniaVariables?.favourites_count || '0')
    .replace(/\{\{\s*cart_count\s*\}\}/g, piniaVariables?.cart_count || '0')
    .replace(/\{\{\s*cart_amount\s*\}\}/g, piniaVariables?.cart_amount || '-')
    .replace(/\{\{\s*reference\s*\}\}/g, piniaVariables?.reference || '')
}


// Method: to declare css variable in the root
export const irisStyleVariables = (layoutColor: string[]) => {
    if (!layoutColor) {
        return 
    }

    const root = document.documentElement
    root.style.setProperty('--iris-color-primary', layoutColor[0])
    root.style.setProperty('--iris-color-secondary', layoutColor[2])
}


/* export const getVal = (base: any, path?: string[], screen = 'desktop') =>{
      return  resolveResponsiveValue(base, screen, path);
} */

export const resolveResponsiveValue = (
  base: any,
  screen: 'mobile' | 'tablet' | 'desktop',
  path?: string[]
) => {
  if (!base || typeof base !== 'object') return base;

  const getValue = (obj: any) => {
    if (!obj || typeof obj !== 'object') return undefined;
    return path ? path.reduce((acc, key) => acc?.[key], obj) : obj;
  };

  // ✅ NEW: If path is undefined and base has direct responsive keys (e.g., { mobile: '...', desktop: '...' }), just return base[screen]
  const isResponsiveObject =
    !path &&
    ['mobile', 'tablet', 'desktop'].some(k => Object.prototype.hasOwnProperty.call(base, k));

  if (isResponsiveObject) {
    return base?.[screen] ?? base?.desktop ?? null;
  }

  // 1. Try current screen
  const currentValue = getValue(base[screen]);
  if (currentValue !== undefined) return currentValue;

  // 2. Fallback to desktop
  if (screen !== 'desktop') {
    const desktopValue = getValue(base.desktop);
    if (desktopValue !== undefined) return desktopValue;
  }

  // 3. Fallback to global
  return getValue(base);
};
