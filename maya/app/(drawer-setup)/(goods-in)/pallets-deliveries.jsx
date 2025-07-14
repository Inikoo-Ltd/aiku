// screens/FulfilmentDeliveries.tsx

import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import PalletDeliveryItem from '@/components/PalletDeliveryItem';
import { createGlobalStyles } from '@/globalStyles';
import { useContext } from 'react';
import {
  View,
  useColorScheme,
} from 'react-native';

import {
  faCheck,
  faCheckDouble,
  faCross,
  faSeedling,
  faShare,
  faSpellCheck,
} from '@/private/fa/pro-light-svg-icons';
import { library } from '@fortawesome/fontawesome-svg-core';
import { useRouter } from 'expo-router';

library.add(faSeedling, faShare, faSpellCheck, faCheck, faCross, faCheckDouble);

const FulfilmentDeliveries = ({ navigation }) => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter()

  return (
    <View style={globalStyles.container}>
      <BaseList
        navigation={navigation}
        urlKey="get-deliveries"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <PalletDeliveryItem
            icon={item.state_icon.icon}
            iconColor={item.state_icon.color}
            title={item.reference}
            subtitle={item.customer_reference || 'No customer reference available'}
            onPress={() => router.replace(`/(show-delivery)/show-delivery-data?id=${item.id}`)}
            globalStyles={globalStyles}
          />
        )}
      />
    </View>
  );
};

export default FulfilmentDeliveries;
