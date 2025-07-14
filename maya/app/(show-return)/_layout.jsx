import { ReturnsProvider } from '@/components/context/return'; // ✅ updated import
import DrawerHeader from '@/components/DrawerHeader';
import { faTruckContainer, faTruckCouch } from '@/private/fa/pro-light-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { Tabs } from 'expo-router';

export default function TabLayout() {
  return (
    <ReturnsProvider> 
      <Tabs>
        <Tabs.Screen
          name="show-return-data" 
          options={{
            title: 'Showcase',
            header: () => <DrawerHeader title="Showcase" />,
            tabBarIcon: ({ color, size }) => (
              <FontAwesomeIcon icon={faTruckCouch} color={color} size={size} />
            ),
          }}
        />
        <Tabs.Screen
          name="pallets-in-return"
          options={{
            title: 'Pallets List',
            header: () => <DrawerHeader title="Pallets List" />,
            tabBarIcon: ({ color, size }) => (
              <FontAwesomeIcon icon={faTruckContainer} color={color} size={size} />
            ),
          }}
        />
      </Tabs>
    </ReturnsProvider>
  );
}
