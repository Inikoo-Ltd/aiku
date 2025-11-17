/**
 * Author: Vika Aqordi
 * Created on 14-11-2025-10h-58m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import _ from 'lodash-es';
window._ = _;

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

declare global {
    interface Window {
        Echo: any
        Pusher: any
    }
}
