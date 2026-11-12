(() => {
    const root = document.documentElement
    const siteRoot = (document.currentScript?.dataset.root ?? '').replace(/\/$/, '')

    const sitePath = href => {
        try {
            const url = new URL(href, location.href)
            if (url.origin !== location.origin || !url.pathname.startsWith(siteRoot)) {
                return null
            }
            return url.pathname.slice(siteRoot.length).replace(/\/$/, '') || '/'
        } catch {
            return null
        }
    }

    const markCurrentNavigation = () => {
        const current = sitePath(location.href)
        const links = [...document.querySelectorAll('.header-nav-item > a[href], .header-nav-folder-item > a[href], .header-menu-nav-item > a[href]')]
            .map(link => ({ link, path: sitePath(link.getAttribute('href')) }))
            .filter(({ link }) => !link.matches('[data-folder-id], [data-action], .user-accounts-text-link, .header-skip-link'))
            .filter(({ path }) => path && (path === current || (path !== '/' && current.startsWith(path + '/'))))
        const longest = Math.max(-1, ...links.map(({ path }) => path.length))

        links.filter(({ path }) => path.length === longest).forEach(({ link }) => {
            link.setAttribute('aria-current', 'page')
            link.closest('.header-nav-item')?.classList.add('header-nav-item--active')
            link.closest('.header-nav-folder-item')?.classList.add('header-nav-folder-item--active')
            link.closest('.header-menu-nav-item')?.classList.add('header-menu-nav-item--active')

            const folder = link.closest('.header-nav-item--folder')
            folder?.classList.add('header-nav-item--active')
            folder?.querySelector('.header-nav-folder-title')?.setAttribute('aria-current', 'true')

            const menuFolder = link.closest('.header-menu-nav-folder[data-folder]')
            if (menuFolder && menuFolder.dataset.folder !== 'root') {
                const folderLink = document.querySelector(`.header-menu [data-folder-id="${CSS.escape(menuFolder.dataset.folder)}"]`)
                folderLink?.setAttribute('aria-current', 'true')
                folderLink?.closest('.header-menu-nav-item')?.classList.add('header-menu-nav-item--active')
            }
        })
    }

    const layoutHeader = () => {
        const header = document.querySelector('.header')
        if (!header) {
            return
        }
        const height = header.offsetHeight + 'px'
        root.style.setProperty('--header-height', height)
        document.querySelectorAll('[data-static-header-offset]').forEach(element => {
            element.style.paddingTop = height
        })
    }

    const layoutScaledText = () => {
        document.querySelectorAll('.sqsrte-scaled-text-container').forEach(container => {
            const text = container.querySelector('.sqsrte-scaled-text')
            if (!text || !container.clientWidth) {
                return
            }
            const blocks = [...text.children]
            const savedWhiteSpace = blocks.map(block => block.style.whiteSpace)
            blocks.forEach(block => {
                block.style.whiteSpace = 'pre'
            })
            text.style.fontSize = '100px'
            const range = document.createRange()
            range.selectNodeContents(text)
            const width = Math.max(...[...range.getClientRects()].map(rect => rect.width), 0)
            blocks.forEach((block, index) => {
                block.style.whiteSpace = savedWhiteSpace[index]
            })
            if (width > 0) {
                text.style.fontSize = Math.floor(1000 * container.clientWidth / width) / 10 + 'px'
            }
        })
    }

    const layoutContainedImages = () => {
        document.querySelectorAll('.fluid-image-component-root').forEach(image => {
            const style = image.style
            const nativeRatio = parseFloat(style.getPropertyValue('--image-component-native-aspect-ratio'))
            if (style.getPropertyValue('--image-component-object-fit').trim() !== 'contain' || !nativeRatio) {
                return
            }
            const { width, height } = image.getBoundingClientRect()
            if (!width || !height) {
                return
            }
            if (nativeRatio > width / height) {
                style.setProperty('--image-component-container-width', '100%')
                style.setProperty('--image-component-container-height', width / nativeRatio + 'px')
            } else {
                style.setProperty('--image-component-container-height', '100%')
                style.setProperty('--image-component-container-width', height * nativeRatio + 'px')
            }
        })
    }

    const carousels = [...document.querySelectorAll('.user-items-list-carousel')].map(carousel => {
        const track = carousel.querySelector('.user-items-list-carousel__slides')
        const slides = [...carousel.querySelectorAll('.user-items-list-carousel__slide')]
        const isInfinite = carousel.dataset.isInfiniteEnabled === 'true'
        const state = { start: 0 }

        const layout = () => {
            if (!track || !slides.length) {
                return
            }
            const visible = getComputedStyle(track).gridTemplateColumns.split(' ').filter(Boolean).length || 1
            const gap = parseFloat(getComputedStyle(track).columnGap) || 0
            const step = slides[0].getBoundingClientRect().width + gap
            const count = slides.length
            slides.forEach((slide, index) => {
                let offset = index - state.start
                if (isInfinite) {
                    offset = ((offset % count) + count) % count
                    if (offset === count - 1 && count > visible + 1) {
                        offset = -1
                    }
                }
                const isNear = offset >= -1 && offset <= visible
                slide.style.transform = `translate3d(${isNear ? offset * step : -9999}px, 0px, 0px)`
            })
        }

        const move = direction => {
            const visible = getComputedStyle(track).gridTemplateColumns.split(' ').filter(Boolean).length || 1
            const last = Math.max(0, slides.length - visible)
            state.start = isInfinite ? (state.start + direction + slides.length) % slides.length : Math.min(last, Math.max(0, state.start + direction))
            layout()
        }

        carousel.querySelectorAll('.user-items-list-carousel__arrow-button--left, .mobile-arrow-button--left').forEach(button => button.addEventListener('click', () => move(-1)))
        carousel.querySelectorAll('.user-items-list-carousel__arrow-button--right, .mobile-arrow-button--right').forEach(button => button.addEventListener('click', () => move(1)))

        return layout
    })

    const folders = () => [...document.querySelectorAll('.header-menu-nav-folder')]
    const rootFolder = () => folders().find(folder => !folder.dataset.folder || folder.dataset.folder === 'root') ?? folders()[0]

    const closeFolders = () => {
        folders().forEach(folder => folder.classList.remove('header-menu-nav-folder--open', 'header-menu-nav-folder--active'))
        rootFolder()?.classList.add('header-menu-nav-folder--active')
    }

    const setMenuOpen = isOpen => {
        document.body.classList.toggle('header--menu-open', isOpen)
        document.querySelectorAll('.header-burger-btn').forEach(button => button.classList.toggle('burger--active', isOpen))
        if (!isOpen) {
            closeFolders()
        }
    }

    document.querySelectorAll('.header-burger-btn').forEach(button => {
        button.addEventListener('click', () => setMenuOpen(!document.body.classList.contains('header--menu-open')))
    })

    document.querySelectorAll('.header-menu [data-folder-id]').forEach(link => {
        link.addEventListener('click', event => {
            const folder = document.querySelector(`.header-menu-nav-folder[data-folder="${CSS.escape(link.dataset.folderId)}"]`)
            if (!folder) {
                return
            }
            event.preventDefault()
            rootFolder()?.classList.add('header-menu-nav-folder--open')
            folder.classList.add('header-menu-nav-folder--active')
        })
    })

    document.querySelectorAll('.header-menu [data-action="back"]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault()
            closeFolders()
        })
    })

    document.querySelectorAll('.sqs-video-wrapper[data-html]').forEach(wrapper => {
        const overlay = wrapper.querySelector('.sqs-video-overlay')
        if (!overlay) {
            return
        }
        overlay.style.cursor = 'pointer'
        overlay.addEventListener('click', () => {
            const template = document.createElement('template')
            template.innerHTML = wrapper.dataset.html
            const frame = template.content.querySelector('iframe')
            if (!frame?.src) {
                return
            }
            const player = new URL(frame.src)
            player.searchParams.set('autoplay', '1')
            const embed = document.createElement('iframe')
            embed.src = player.href
            embed.title = frame.title || 'Video'
            embed.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
            embed.allowFullscreen = true
            embed.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0'
            overlay.replaceWith(embed)
        })
    })

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault()
            if (form.querySelector('.static-site-form-notice')) {
                return
            }
            const notice = document.createElement('p')
            notice.className = 'static-site-form-notice'
            notice.setAttribute('role', 'status')
            notice.textContent = form.dataset.error404 || 'This form has not been configured yet.'
            form.appendChild(notice)
        })
    })

    document.querySelectorAll('.accordion-item__click-target').forEach(button => {
        button.addEventListener('click', () => {
            const isOpen = button.getAttribute('aria-expanded') !== 'true'
            const item = button.closest('.accordion-item')
            button.setAttribute('aria-expanded', String(isOpen))
            if (isOpen) {
                item?.setAttribute('data-is-open', 'true')
            } else {
                item?.removeAttribute('data-is-open')
            }
            document.getElementById(button.getAttribute('aria-controls') ?? '')?.classList.toggle('accordion-item__dropdown--open', isOpen)
        })
    })

    const layout = () => {
        layoutHeader()
        layoutScaledText()
        layoutContainedImages()
        carousels.forEach(layoutCarousel => layoutCarousel())
    }

    markCurrentNavigation()
    layout()
    document.fonts?.ready.then(layout)
    window.addEventListener('load', layout)
    window.addEventListener('resize', layout)
})()
