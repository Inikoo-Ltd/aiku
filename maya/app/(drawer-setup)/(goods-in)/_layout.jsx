import DrawerHeader from '@/components/DrawerHeader';
import { faTruckContainer, faTruckCouch } from '@/private/fa/pro-light-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Tabs } from 'expo-router';

export default function TabLayout() {
  return (
    <Tabs>
      <Tabs.Screen
        name="pallets-deliveries"
        options={{
          title: 'Pallets Deliveries',
          header: () => <DrawerHeader title="Pallets Deliveries" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faTruckCouch} color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="stock-deliveries"
        options={{
          title: 'Stock Deliveries',
          header: () => <DrawerHeader title="Stock Deliveries" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faTruckContainer} color={color} size={size} />
          ),
        }}
      />
    </Tabs>
  );
}
