import request from '@/utils/Request';
import { merge } from 'lodash';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

export function retrieveProfile(options) {
    options = merge(
        {
            onSuccess: window.noop,
            onFailed: window.noop,
        },
        options,
    );

    return request({
        urlKey: 'get-profile',
        headers: {
            Authorization: `Bearer ${options.accessToken}`,
        },
        onFailed: options.onFailed,
        onSuccess: userProfileRes => {
            options.onSuccess({...userProfileRes});
        },
    });
}


export function logout(signOut) {
    console.log('Logging out...');

    if (signOut) {
        request({
            urlKey: 'logout',
            method: 'post',
            onSuccess: () => {
                signOut();
            },
            onFailed: error => {
                console.log(error);
                if (error.status == 401) {
                    signOut();
                } else {
                    Toast.show({
                        type: ALERT_TYPE.DANGER,
                        title: 'Error',
                        textBody: error.detail?.message || 'Failed to logout',
                    });
                }
            },
        });
    } else {
        console.log('else in navigation')
        // if (navigationRef.isReady()) {
        //     navigationRef.navigate('session-expired');
        // }
    }
}
