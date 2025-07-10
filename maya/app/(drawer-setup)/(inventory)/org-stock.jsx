import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import OrgStockItem from '@/components/OrgStockItem';
import { createGlobalStyles } from '@/globalStyles';
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';
// import { useRouter } from 'expo-router'; // Uncomment if needed for navigation

const OrgStocksScreens = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-org-stocks"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <OrgStockItem
            item={item}
            onPress={() => null}
          />
        )}
      />
    </View>
  );
};

export default OrgStocksScreens;
