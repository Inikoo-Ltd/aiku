import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import LocationItem from '@/components/LocationItem';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router'; // Uncomment if needed
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const LocationsScreens = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter(); 

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-locations"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <LocationItem
            item={item}
            onPress={() => router.push(`/show-location?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default LocationsScreens;
