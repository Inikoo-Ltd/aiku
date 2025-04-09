import React from 'react';
import { TouchableOpacity } from 'react-native';
import { navigationRef } from '@/src/utils/NavigationService';
import Home from '@/src/screens/Home';
import InventoryStackScreen from '@/src/screens/routes/InventoryStackScreen';
import GoodsInStackScreen from '@/src/screens/routes/GoodsInStackScreen';
import GoodsOutStackScreen from '@/src/screens/routes/GoodsOutStackScreen';
import LocationsStackScreen from '@/src/screens/routes/LocationsStackScreen';

import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import {
    faHome,
    faPalletAlt,
    faInventory,
    faArrowToBottom,
    faArrowFromLeft,
    faBarcodeRead,
} from '@/private/fa/pro-regular-svg-icons';

const fulfilmentMenu = (fulfilment,warehouse) => {
    const navigation = navigationRef;

    return [
        {
            name: 'Home',
            component: Home,
            permission: [],
            options: {
                drawerIcon: ({ color }) => (
                    <FontAwesomeIcon icon={faHome} size={22} color={color} />
                ),
                headerRight: () => (
                    <TouchableOpacity
                        className="mx-3"
                        onPress={() => navigation.navigate('global-scanner')}
                    >
                        <FontAwesomeIcon icon={faBarcodeRead} size={22} />
                    </TouchableOpacity>
                ),
            },
        },
        {
            name: 'Inventory',
            permission: [
                `fulfilment-shop.${fulfilment?.id}`,
                `fulfilment-shop.${fulfilment?.id}.view`,
                `fulfilment.${warehouse?.id}`,
                `supervisor-fulfilment-shop.${warehouse?.id}`
            ],
            component: InventoryStackScreen,
            options: {
                drawerIcon: ({ color }) => (
                    <FontAwesomeIcon icon={faInventory} size={22} color={color} />
                ),
                headerRight: () => (
                    <TouchableOpacity
                        className="mx-3"
                        onPress={() => navigation.navigate('global-scanner')}
                    >
                        <FontAwesomeIcon icon={faBarcodeRead} size={22} />
                    </TouchableOpacity>
                ),
            },
        },
        {
            name: 'Location',
            permission: [
                `fulfilment-shop.${fulfilment?.id}`,
                `fulfilment-shop.${fulfilment?.id}.view`,
                `fulfilment.${warehouse?.id}`,
                `supervisor-fulfilment-shop.${warehouse?.id}`
            ],
            component: LocationsStackScreen,
            options: {
                drawerIcon: ({ color }) => (
                    <FontAwesomeIcon icon={faPalletAlt} size={22} color={color} />
                ),
                headerRight: () => (
                    <TouchableOpacity
                        className="mx-3"
                        onPress={() => navigation.navigate('global-scanner')}
                    >
                        <FontAwesomeIcon icon={faBarcodeRead} size={22} />
                    </TouchableOpacity>
                ),
            },
        },
        {
            name: 'Goods In',
            permission: [
                `fulfilment-shop.${fulfilment?.id}`,
                `fulfilment-shop.${fulfilment?.id}.view`,
                `fulfilment.${warehouse?.id}`,
                `supervisor-fulfilment-shop.${warehouse?.id}`
            ],
            component: GoodsInStackScreen,
            options: {
                drawerIcon: ({ color }) => (
                    <FontAwesomeIcon icon={faArrowToBottom} size={22} color={color} />
                ),
                headerRight: () => (
                    <TouchableOpacity
                        className="mx-3"
                        onPress={() => navigation.navigate('global-scanner')}
                    >
                        <FontAwesomeIcon icon={faBarcodeRead} size={22} />
                    </TouchableOpacity>
                ),
            },
        },
        {
            name: 'Goods Out',
            permission: [
                `fulfilment-shop.${fulfilment?.id}`,
                `fulfilment-shop.${fulfilment?.id}.view`,
                `fulfilment.${warehouse?.id}`,
                `supervisor-fulfilment-shop.${warehouse?.id}`
            ],
            component: GoodsOutStackScreen,
            options: {
                drawerIcon: ({ color }) => (
                    <FontAwesomeIcon icon={faArrowFromLeft} size={22} color={color} />
                ),
                headerRight: () => (
                    <TouchableOpacity
                        className="mx-3"
                        onPress={() => navigation.navigate('global-scanner')}
                    >
                        <FontAwesomeIcon icon={faBarcodeRead} size={22} />
                    </TouchableOpacity>
                ),
            },
        },
    ];
};

export default fulfilmentMenu;
