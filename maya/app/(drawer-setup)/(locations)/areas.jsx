import AreaItem from '@/components/AreaItem';
import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router'; // Uncomment if needed
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const AreasScreens = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter(); // Uncomment if you need to navigate

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-areas"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <AreaItem
            item={item}
            onPress={() => router.push(`/show-area?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default AreasScreens;
