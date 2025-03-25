import React, {useContext} from 'react';
import {createStackNavigator} from '@react-navigation/stack';

import DeliveryStackScreen from '@/src/screens/routes/DeliveryStackScreens';
import RetrunStackScreen from '@/src/screens/routes/RetrunsStackScreens';
import PalletStackScreen from '@/src/screens/routes/PalletStackScreen';
import ItemStackScreens from '@/src/screens/routes/ItemStackScreens';
import Settings from '@/src/screens/Settings';
import Organisation from '@/src/screens/Organisation';
import Warehouse from '@/src/screens/Warehouse';
import Fulfilment from '@/src/screens/Fulfilment';
import ShowDeliveryNote from '@/src/screens/Fulfilment/DeliveryNote/ShowDeliveryNote';
import LocationStackScreens from '@/src/screens/routes/LocationStackScreens';
import ShowArea from '@/src/screens/Fulfilment/Area/ShowArea';
import ShowStockDelivery from '@/src/screens/Fulfilment/Stock/ShowStockDelivery';
import SessionExpired from '@/src/screens/SessionExpired';
import ShowOrgStock from '@/src/screens/Fulfilment/OrgStock/ShowOrgStock';
import EditProfile from '@/src/screens/EditProfile';
import GlobalScanner from '@/src/screens/GlobalScanner';
import AuditSku from '@/src/screens/Fulfilment/Pallet/AuditSku';

/* import ShowLocation from '@/src/screens/Fulfilment/Location/ShowLocation'; */
/* import Scanner from '@/src/screens/Scanner'; */

import DrawerScreens from '@/src/screens/routes/drawerScreens';
import {AuthContext} from '@/src/components/Context/context';

const Stack = createStackNavigator();

const HomeStack = () => {
      console.log('ooo',useContext(AuthContext))

    return (
        <Stack.Navigator
            screenOptions={{
                headerShown: false,
                /*    headerStyle: { backgroundColor: '#4F46E5' },
        headerTintColor: '#fff', */
            }}>
            <Stack.Screen name="home-drawer" component={DrawerScreens} />
            <Stack.Screen name="setting" component={Settings} />
            <Stack.Screen
                name="show-pallet"
                component={PalletStackScreen}
                options={{
                    headerShown: true,
                    title: 'Pallet',
                }}
            />
            <Stack.Screen
                name="show-fulfilment-delivery"
                component={DeliveryStackScreen}
                options={{
                    headerShown: true,
                    title: 'Fulfilment Delivery',
                }}
            />
            <Stack.Screen
                name="show-fulfilment-return"
                component={RetrunStackScreen}
                options={{
                    headerShown: true,
                    title: 'Fulfilment Returns',
                }}
            />
            <Stack.Screen
                name="organisation"
                component={Organisation}
                options={{
                    headerShown: true,
                    title: 'Organisation/Agents',
                }}
            />
            <Stack.Screen
                name="fulfilment"
                component={Fulfilment}
                options={{
                    headerShown: true,
                    title: 'Fulfilment',
                }}
            />
            <Stack.Screen
                name="warehouse"
                component={Warehouse}
                options={{
                    headerShown: true,
                    title: 'Warehouse',
                }}
            />
            {/*  inventory */}
            <Stack.Screen
                name="show-delivery-note"
                component={ShowDeliveryNote}
                options={{
                    headerShown: true,
                    title: 'Delivery Note',
                }}
            />
            <Stack.Screen
                name="show-location"
                component={LocationStackScreens}
                options={{
                    headerShown: true,
                    title: 'Location',
                }}
            />
            <Stack.Screen
                name="show-area"
                component={ShowArea}
                options={{
                    headerShown: true,
                    title: 'Area',
                }}
            />
            <Stack.Screen
                name="show-stock-delivery"
                component={ShowStockDelivery}
                options={{
                    headerShown: true,
                    title: 'Stock Delivery',
                }}
            />
            <Stack.Screen
                name="show-stored-item"
                component={ItemStackScreens}
                options={{
                    headerShown: true,
                    title: 'SKU',
                }}
            />
            <Stack.Screen
                name="global-scanner"
                component={GlobalScanner}
                options={{
                    /*  headerShown: true, */
                    title: 'scanner',
                }}
            />
         {/*    <Stack.Screen
                name="scanner"
                component={Scanner}
                options={{
                }}
            /> */}
            <Stack.Screen
                name="show-org-stock"
                component={ShowOrgStock}
                options={{
                    headerShown: true,
                    title: 'OrgStock',
                }}
            />
            <Stack.Screen
                name="session-expired"
                component={SessionExpired}
                options={{
                    headerShown: false,
                    title: 'Expired Token',
                }}
            />
            <Stack.Screen
                name="edit-profile"
                component={EditProfile}
                options={{
                    headerShown: true,
                    title: 'Edit Profile',
                }}
            />
             <Stack.Screen
                name='audit-sku'
                component={AuditSku}
                options={{
                    headerShown: true,
                    title: 'Audit SKU',
                }}
            />
        </Stack.Navigator>
    );
};

export default HomeStack;
