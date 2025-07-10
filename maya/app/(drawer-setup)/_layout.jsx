import { AuthContext } from '@/components/context/AuthContext';
import CustomDrawer from '@/components/CustomDrawer';
import DrawerHeader from '@/components/DrawerHeader';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Drawer } from 'expo-router/drawer';
import { useContext } from 'react';

import {
  faArrowFromLeft,
  faArrowToBottom,
  faBuilding,
  faHouse,
  faInventory,
  faPalletAlt,
  faWarehouse,
  faWarehouseAlt,
} from '@/private/fa/pro-light-svg-icons';

export default function DrawerLayout() {
  const { organisation, warehouse } = useContext(AuthContext);

  return (
    <Drawer
      drawerContent={(props) => <CustomDrawer {...props} />}
      screenOptions={({ route }) => ({
        header: () => <DrawerHeader title={getTitle(route.name)} />,
      })}
    >
      <Drawer.Screen
        name="home/index"
        options={{
          title: 'Home',
          showInDrawer: true,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faHouse} size={size} color={color} />
          ),
        }}
      />
      <Drawer.Screen
        name="organisation/index"
        options={{
          title: 'Organisation',
          showInDrawer: true,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faBuilding} size={size} color={color} />
          ),
        }}
      />
      <Drawer.Screen
        name="fulfilment/index"
        options={{
          title: 'Fulfilments',
          showInDrawer: !!organisation,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faWarehouseAlt} size={size} color={color} />
          ),
        }}
      />
      <Drawer.Screen
        name="warehouse/index"
        options={{
          title: 'Warehouses',
          showInDrawer: !!organisation,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faWarehouse} size={size} color={color} />
          ),
        }}
      />
      <Drawer.Screen
        name="(inventory)"
        options={{
          title: 'Inventory',
          headerShown: false,
          showInDrawer: !!warehouse,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faPalletAlt} size={size} color={color} />
          ),
        }}
      />
      <Drawer.Screen
        name="(locations)"
        options={{
          title: 'Locations',
          headerShown: false,
          showInDrawer: !!warehouse,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faInventory} size={size} color={color} />
          ),
        }}
      />
      <Drawer.Screen
        name="(goods-in)"
        options={{
          title: 'Goods In',
          headerShown: false,
          showInDrawer: !!warehouse,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faArrowToBottom} size={size} color={color} />
          ),
        }}
      />
      <Drawer.Screen
        name="(goods-out)"
        options={{
          title: 'Goods Out',
          headerShown: false,
          showInDrawer: !!warehouse,
          drawerIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faArrowFromLeft} size={size} color={color} />
          ),
        }}
      />
    </Drawer>
  );
}

function getTitle(name) {
  const map = {
    'home/index': 'Home',
    'organisation/index': 'Organisation',
    'fulfilment/index': 'Fulfilments',
    'warehouse/index': 'Warehouses',
  };
  return map[name] || 'App';
}
