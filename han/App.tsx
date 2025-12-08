import * as React from 'react';
import {NavigationContainer} from '@react-navigation/native';
import { Provider } from "react-redux";
import { Store } from "./src/store";
import AuthNavigator from './src/routes/AuthNavigator';
import FlashMessage from "react-native-flash-message";

export default function App() {
  return (
    <Provider store={Store}>
      <NavigationContainer>
        <AuthNavigator />
      </NavigationContainer>
      <FlashMessage position="top" />
    </Provider>
  )
}
