import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {COLORS, ROUTES} from '~/constants';
import {useNavigation} from '@react-navigation/native';
import React, {useEffect, useState} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {ActivityIndicator, StyleSheet, View} from 'react-native';

const Tab = createBottomTabNavigator();

function BottomTabNavigator(props: object) {
  const [finalRoutes, setFinalRoutes] = useState([]);
  const navigation = useNavigation();

  const checkPermissions = async (routes: Array) => {
    const value = await AsyncStorage.getItem('@AuthenticationToken:Key');
    let final = []; // Use let instead of const
    if (value) {
      const user = JSON.parse(value);
      for (const route of routes) {
        if (route.permissions) {
          if (route.permissions.some(item => user.permissions.includes(item)))
            final.push(route);
        } else final.push(route);
      }
    } else final = routes;
    setFinalRoutes(final);
  };

  useEffect(() => {
    const fetchData = async () => {
      await checkPermissions(props.extraData.components);
    };
    fetchData();
  }, []);

  return finalRoutes.length > 0 ? (
    <Tab.Navigator
      screenOptions={({route}) => ({
        tabBarInactiveTintColor: COLORS.dark,
        tabBarActiveTintColor: COLORS.primary,
      })}>
      {finalRoutes.map((item, index) => {
        return (
          <Tab.Screen
            key={index}
            name={item.name}
            component={item.component}
            options={{...item.option}}
            initialParams={{...props.route.params}}
          />
        );
      })}
    </Tab.Navigator>
  ) : (
    <View style={styles.loadingContainer}>
      <ActivityIndicator size="large" color={COLORS.primary} />
    </View>
  );
}

export default BottomTabNavigator;

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});
