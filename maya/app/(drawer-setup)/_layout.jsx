// app/(drawer)/_layout.tsx
import CustomDrawer from '@/components/CustomDrawer';
import { Drawer } from 'expo-router/drawer';


export default function DrawerLayout() {
  return (
    <Drawer
      drawerContent={(props) => <CustomDrawer {...props} />}
      screenOptions={{ headerShown: true }}
    >
      <Drawer.Screen name="home/index" options={{ title: "Home" }} />
      <Drawer.Screen name="organisation/index" options={{ title: "Organisation" }} />
      <Drawer.Screen name="fulfilment/index" options={{ title: "Fulfilments" }} />
      <Drawer.Screen name="warehouse/index" options={{ title: "Warehouses" }} />
    </Drawer>
  );
}
