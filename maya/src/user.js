import {merge} from 'lodash-es';
import request from '@/src/utils/Request';
import {navigationRef} from '@/src/utils/NavigationService';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';

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

/* export function revokeToken(
    token = getDvaApp()._store.getState().user?.access_token,
    removeSession = true,
  ) {
    request({
      method: 'post',
      urlKey: 'logout',
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      data: {
        token,
        client_id: appConfig.oauth.id,
        client_secret: appConfig.oauth.secret,
      },
      onBefore(options) {
        delete options.headers['Authorization'];
      },
    }).then();
  
    if (removeSession) getDvaApp()._store.dispatch({ type: 'user/remove' });
  
    return null;
  } */

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
        if (navigationRef.isReady()) {
            navigationRef.navigate('session-expired');
        }
    }
}
