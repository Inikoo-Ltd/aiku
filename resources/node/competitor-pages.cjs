/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

const puppeteer = require('puppeteer')

const BLOCKED_WORDS = /captcha|verify you are (a )?human|unusual traffic|access denied|are you a robot|slide to verify|drag the slider/i
const PASSWORD_FIELD = 'input[type=password]'
const USERNAME_FIELD = 'input[type=email], input[name*=email i], input[id*=email i], input[name*=user i], input[id*=user i], input[name*=login i], input[type=text]'

const readStdin = async () => {
    let input = ''
    for await (const chunk of process.stdin) {
        input += chunk
    }
    return JSON.parse(input)
}

const visible = async (page, selector) => {
    for (const handle of await page.$$(selector)) {
        if (await handle.isVisible()) {
            return handle
        }
    }
    return null
}

const settle = (page) => page.waitForNetworkIdle({idleTime: 800, timeout: 20000}).catch(() => null)

const logIn = async (page, login) => {
    await page.goto(login.url, {waitUntil: 'networkidle2', timeout: 45000})

    const username = await visible(page, USERNAME_FIELD)
    if (!username) {
        return 'no login form'
    }
    await username.type(login.username, {delay: 40})

    let password = await visible(page, PASSWORD_FIELD)
    if (!password) {
        await page.keyboard.press('Enter')
        await page.waitForSelector(PASSWORD_FIELD, {visible: true, timeout: 15000}).catch(() => null)
        password = await visible(page, PASSWORD_FIELD)
    }
    if (!password) {
        return 'no password field'
    }
    await password.type(login.password, {delay: 40})
    await page.keyboard.press('Enter')
    await settle(page)

    if (BLOCKED_WORDS.test(await page.evaluate(() => document.body.innerText))) {
        return 'blocked'
    }

    return await visible(page, PASSWORD_FIELD) ? 'wrong login' : null
}

const read = async (page, url) => {
    const response = await page.goto(url, {waitUntil: 'networkidle2', timeout: 45000})
    const content = await page.evaluate(() => ({
        text: document.body.innerText.replace(/\n{2,}/g, '\n').slice(0, 30000),
        links: [...new Map([...document.querySelectorAll('a[href]')]
            .map(a => [a.href, (a.innerText || a.title || '').trim().replace(/\s+/g, ' ')])
            .filter(([href, text]) => href.startsWith('http') && text.length > 3)).entries()]
            .slice(0, 200)
            .map(([href, text]) => ({href, text: text.slice(0, 200)})),
    }))

    return {
        url,
        final_url: page.url(),
        status: response ? response.status() : null,
        blocked: BLOCKED_WORDS.test(content.text.slice(0, 3000)),
        ...content,
    }
}

const main = async () => {
    const input = await readStdin()
    const browser = await puppeteer.launch({headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']})
    const result = {login_error: null, cookies: null, pages: []}

    try {
        const page = await browser.newPage()
        await page.setUserAgent(input.user_agent)
        await page.setViewport({width: 1366, height: 900})

        if (input.cookies?.length) {
            await browser.setCookie(...input.cookies)
        }

        if (input.login) {
            result.login_error = await logIn(page, input.login)
        }

        if (!result.login_error) {
            for (const url of input.urls) {
                try {
                    const pageRead = await read(page, url)
                    result.pages.push(pageRead)
                    if (pageRead.blocked) {
                        break
                    }
                } catch (error) {
                    result.pages.push({url, error: error.message})
                }
            }
        }

        result.cookies = await browser.cookies()
    } finally {
        await browser.close()
    }

    process.stdout.write(JSON.stringify(result))
}

main().catch(error => {
    process.stdout.write(JSON.stringify({error: error.message}))
    process.exit(1)
})
