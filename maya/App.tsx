import React, {useEffect, useReducer, useMemo} from 'react';
import {View, ActivityIndicator} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {GluestackUIProvider} from '@/src/components/ui/gluestack-ui-provider';
import MainStackScreen from '@/src/screens/routes/MainStackScreen';
import RootStackScreen from '@/src/screens/routes/RootStackScreen';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {getData} from '@/src/utils/AsyncStorage';
import {AuthContext} from '@/src/components/Context/context';
import {loginReducer} from '@/src/Reducer/loginReducer';
import {AlertNotificationRoot} from 'react-native-alert-notification';
import { navigationRef } from '@/src/utils/NavigationService';
import {retrieveProfile} from '@/src/user';
import {ALERT_TYPE, Toast} from 'react-native-alert-notification';
import './global.css';

const Stack = createNativeStackNavigator();

function App(): React.JSX.Element {
  const initialLoginState = {
    isLoading: true,
    userData: null,
    userToken: null,
    organisation: null,
    fulfilment: null,
    warehouse: null,
  };
  const [loginState, dispatch] = useReducer(loginReducer, initialLoginState);

  const authContext = useMemo(
    () => ({
      signIn: async user => {
        try {
          await AsyncStorage.setItem('persist:user', JSON.stringify(user));
        } catch (e) {
          console.log('Error storing token:', e);
        }
        dispatch({
          type: 'LOGIN',
          token: user.token,
          userData: user,
          organisation: user.organisation,
          fulfilment: null,
          warehouse: null,
          isLoading:false
        });
      },
      setOrganisation: async user => {
        try {
          await AsyncStorage.setItem('persist:user', JSON.stringify(user));
        } catch (e) {
          console.log('Error storing token:', e);
        }
        dispatch({
          type: 'SET_ORGANISATION',
          token: user.token,
          userData: user,
          organisation: user.organisation,
          fulfilment: null,
          warehouse: null,
          isLoading:false
        });
      },
      setFulfilmentWarehouse: async user => {
        try {
          await AsyncStorage.setItem('persist:user', JSON.stringify(user));
        } catch (e) {
          console.log('Error storing token:', e);
        }
        dispatch({
          type: 'SET_FULFILMENT_WAREHOUSE',
          token: user.token,
          userData: user,
          organisation: user.organisation,
          fulfilment: user.fulfilment,
          warehouse: user.warehouse,
          isLoading:false
        });
      },
      signOut: async () => {
        try {
          await AsyncStorage.removeItem('persist:user');
        } catch (e) {
          console.log('Error removing token:', e);
        }
        dispatch({type: 'LOGOUT'});
      },
      userData: loginState.userData,  // ✅ Ambil dari loginState
      organisation: loginState.organisation,  // ✅ Ambil dari loginState
      fulfilment: loginState.fulfilment,  // ✅ Ambil dari loginState
      warehouse: loginState.warehouse,  // ✅ Ambil dari loginState
      isLoading: loginState.isLoading,  // ✅ Ambil dari loginState
    }),
    [loginState],
  );
  

  useEffect(() => {
    const loadUserToken = async () => {
        try {
            const storedUser = await getData('persist:user');
            console.log('Stored User:', storedUser); // ✅ Check if data is null or undefined

            if (!storedUser) {
                dispatch({ type: 'LOGOUT' }); // Ensure app doesn't stay stuck
                return;
            }

            let user = storedUser;

            await retrieveProfile({
                accessToken: storedUser.token,
                onSuccess: profileRes => {
                    console.log('Profile Response:', profileRes);

                    let organisation = profileRes.data.organisations.find(
                        item => item.code === storedUser.organisation.code
                    );

                    user = { ...storedUser, ...profileRes.data, organisation };
                    
                    dispatch({
                        type: 'RETRIEVE_TOKEN',
                        token: user.token,
                        userData: user,
                        organisation: user.organisation,
                        fulfilment: user.fulfilment,
                        warehouse: user.warehouse,
                        isLoading: false, // ✅ Ensure this is false
                    });
                },
                onFailed: err => {
                    Toast.show({
                        type: ALERT_TYPE.DANGER,
                        title: 'Error',
                        textBody: err?.data.message || 'Failed to update profile data',
                    });
                    dispatch({
                      type: 'RETRIEVE_TOKEN',
                      token: user.token,
                      userData: user,
                      organisation: user.organisation,
                      fulfilment: user.fulfilment,
                      warehouse: user.warehouse,
                      isLoading: false, // ✅ Ensure this is false
                  });
                },
            });

        } catch (error) {
            console.error('Error retrieving token:', error);
            /* dispatch({ type: 'LOGOUT' });  */
        }
    };

    loadUserToken();
}, []);



  if (loginState.isLoading) {
    return (
      <View style={{flex: 1, justifyContent: 'center', alignItems: 'center'}}>
        <ActivityIndicator size="large" />
      </View>
    );
  }


  return (
    <GluestackUIProvider>
      <AlertNotificationRoot>
        <AuthContext.Provider value={authContext}>
          <NavigationContainer ref={navigationRef}>
            {loginState.userToken !== null ? (
              <MainStackScreen />
            ) : (
              <RootStackScreen />
            )}
          </NavigationContainer>
        </AuthContext.Provider>
      </AlertNotificationRoot>
    </GluestackUIProvider>
  );
}

export default App;
