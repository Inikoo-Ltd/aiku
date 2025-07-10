import DrawerHeader from '@/components/DrawerHeader';
import { faInventory, faMapSigns } from '@/private/fa/pro-light-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Tabs } from 'expo-router';

export default function TabLayout() {
  return (
    <Tabs>
      <Tabs.Screen
        name="areas"
        options={{
          title: 'Areas',
          header: () => <DrawerHeader title="Areas" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faMapSigns} size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="locations"
        options={{
          title: 'Locations',
          header: () => <DrawerHeader title="Locations" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faInventory} size={size} color={color} />
          ),
        }}
      />
    </Tabs>
  );
}
