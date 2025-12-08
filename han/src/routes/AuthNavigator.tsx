import React, { useEffect, useState } from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import ListRoutes from './ListRoutes';
import { ROUTES } from '~/constants';
import BottomNavigation from './BottomNavigation';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useDispatch } from 'react-redux';
import Action from '~/store/Action';
import { UpdateCredential } from '~/utils/auth';

const Stack = createNativeStackNavigator();

function AuthNavigator() {
  const [userToken, setUserToken] = useState(null);
  const [finalRoutes, setFinalRoutes] = useState(ListRoutes.routes);
  const [BottomNavigatorRoutes, setBottomNavigatorRoutes] = useState(ListRoutes.BottomNavigatorRoutes);
  const [isLoading, setIsLoading] = useState(true); // Add loading state
  const dispatch = useDispatch();

  const checkuser = async () => {
    try {
      const value = await AsyncStorage.getItem('@AuthenticationToken:Key');
      if (!value) {
        setUserToken(null);
      } else {
        const data = JSON.parse(value);
        const newData = await UpdateCredential(data.token);
        if (newData.status === 'Success' && data.token) {
          dispatch(Action.CreateUserSessionProperties(data));
          setUserToken(data);
        }
      }
    } catch (error) {
      console.error('Error fetching credentials from AsyncStorage:', error);
      setUserToken(null);
    } finally {
      setIsLoading(false);
    }
  };

  const checkPermissions = async (routes) => {
    const value = await AsyncStorage.getItem('@AuthenticationToken:Key');
    let final = [];
    if (value) {
      const user = JSON.parse(value);
      for (const route of routes) {
        if (route.permissions) {
          if (route.permissions.some(item => user.permissions.includes(item)))
            final.push(route);
        } else final.push(route);
      }
    } else {
      final = routes;
    }
    setFinalRoutes(final);
  };

  useEffect(() => {
    const fetchData = async () => {
      await checkuser();
      checkPermissions(ListRoutes.routes);
    };
    fetchData();
  }, []);

  if (isLoading) {
    // You can render a loading indicator here if needed
    return null;
  }

  return (
    <Stack.Navigator initialRouteName={userToken ? ROUTES.BOTTOMHOME : ROUTES.LOGIN}>
      {finalRoutes.map((item, index) => (
        <Stack.Screen
          key={index}
          name={item.name}
          component={item.component}
          options={{ ...item.option }}
        />
      ))}

      {BottomNavigatorRoutes.map((item, index) => (
        <Stack.Screen
          key={index}
          name={item.name}
          options={item.option}
        >
          {(props) => (
            <BottomNavigation {...props} extraData={{ ...item }} />
          )}
        </Stack.Screen>
      ))}
    </Stack.Navigator>
  );
}

export default AuthNavigator;
