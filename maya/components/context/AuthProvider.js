import AsyncStorage from '@react-native-async-storage/async-storage';
import { useEffect, useMemo, useReducer } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { ALERT_TYPE, Toast } from 'react-native-alert-notification';

import { loginReducer } from '@/reducer/loginReducer';
import { getData } from '@/utils/AsyncStorage';
import { retrieveProfile } from '@/utils/user'; // Replace with real function
import { useRouter } from 'expo-router';
import { AuthContext } from './AuthContext';


export const AuthProvider = ({ children }) => {
  const initialLoginState = {
    isLoading: true,
    userData: null,
    userToken: null,
    organisation: null,
    fulfilment: null,
    warehouse: null,
  };
  const router = useRouter();

  const [loginState, dispatch] = useReducer(loginReducer, initialLoginState);

  const authContext = useMemo(() => ({
    signIn: async (user) => {
      await AsyncStorage.setItem('persist:user', JSON.stringify(user));
      dispatch({ type: 'LOGIN', token: user.token, userData: user, organisation: user.organisation });
    },
    setOrganisation: async (user) => {
      await AsyncStorage.setItem('persist:user', JSON.stringify(user));
      dispatch({ type: 'SET_ORGANISATION', ...user });
    },
    setFulfilmentWarehouse: async (user) => {
      await AsyncStorage.setItem('persist:user', JSON.stringify(user));
      dispatch({ type: 'SET_FULFILMENT_WAREHOUSE', ...user });
    },
    signOut: async () => {
      await AsyncStorage.removeItem('persist:user');
      dispatch({ type: 'LOGOUT' });
      router.replace('/manual-login'); 
    },
    ...loginState,
  }), [loginState]);

  useEffect(() => {
    const loadUserToken = async () => {
      try {
        const storedUser = await getData('persist:user');
        if (!storedUser) return dispatch({ type: 'LOGOUT' });

        let user = storedUser;

        await retrieveProfile({
          accessToken: storedUser.token,
          onSuccess: (profileRes) => {
            const organisation = profileRes.data.organisations.find(
              item => item.code === storedUser.organisation?.code
            );
            user = { ...storedUser, ...profileRes.data, organisation };
            dispatch({ type: 'RETRIEVE_TOKEN', token: user.token, userData: user, organisation });
          },
          onFailed: (err) => {
            Toast.show({
              type: ALERT_TYPE.DANGER,
              title: 'Error',
              textBody: err?.data?.message || 'Failed to update profile data',
            });
            dispatch({ type: 'RETRIEVE_TOKEN', token: user.token, userData: user, organisation: user.organisation });
          }
        });
      } catch (err) {
        console.error('Error loading user:', err);
        dispatch({ type: 'LOGOUT' });
      }
    };

    loadUserToken();
  }, []);

  if (loginState.isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return (
    <AuthContext.Provider value={authContext}>
      {children}
    </AuthContext.Provider>
  );
};
