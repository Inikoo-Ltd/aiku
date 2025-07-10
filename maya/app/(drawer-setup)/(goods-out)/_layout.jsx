import DrawerHeader from '@/components/DrawerHeader';
import { faSignOut, faTruck } from '@/private/fa/pro-light-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Tabs } from 'expo-router';

export default function TabLayout() {
  return (
    <Tabs>
      <Tabs.Screen
        name="delivery-note"
        options={{
          title: 'Delivery Note',
          header: () => <DrawerHeader title="Delivery Note" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faTruck} color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="pallets-returns"
        options={{
          title: 'Pallets Returns',
          header: () => <DrawerHeader title="Pallets Returns" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faSignOut} color={color} size={size} />
          ),
        }}
      />
    </Tabs>
  );
}
