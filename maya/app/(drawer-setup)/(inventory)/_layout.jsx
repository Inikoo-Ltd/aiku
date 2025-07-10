import DrawerHeader from '@/components/DrawerHeader';
import { faBoxesAlt, faNarwhal, faPallet } from '@/private/fa/pro-light-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Tabs } from 'expo-router';

export default function TabLayout() {
  return (
    <Tabs>
      <Tabs.Screen
        name="org-stock"
        options={{
          title: 'Org Stock',
          header: () => <DrawerHeader title="Org Stock" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faBoxesAlt} color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="pallets"
        options={{
          title: 'Pallets',
          header: () => <DrawerHeader title="Pallets" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faPallet} color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="stored-items"
        options={{
          title: 'SKU',
          header: () => <DrawerHeader title="SKU" />,
          tabBarIcon: ({ color, size }) => (
            <FontAwesomeIcon icon={faNarwhal} color={color} size={size} />
          ),
        }}
      />
    </Tabs>
  );
}
