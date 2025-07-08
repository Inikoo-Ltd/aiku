import { AuthContext } from '@/components/context/AuthContext';
import globalStyles from '@/globalStyles';
import { faCheckCircle } from '@fortawesome/free-solid-svg-icons/faCheckCircle';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useRouter } from 'expo-router';
import { useContext, useState } from 'react';
import { FlatList, Image, Text, TouchableOpacity, View } from 'react-native';

const GroupItem = ({ item, selectedOrganisation }) => {
  const [imageError, setImageError] = useState(false);
  const { setOrganisation, userData } = useContext(AuthContext);
  const router = useRouter();

  const handleImageError = () => setImageError(true);

  const onPickOrganisation = async () => {
    const updatedUser = { ...userData, organisation: item };
    await setOrganisation(updatedUser);

    if (item.fulfilments?.length > 0) {
      router.push('/(drawer-setup)/fulfilment');
    } else {
      router.push('/(drawer-setup)/warehouse');
    }
  };

  const isActive = selectedOrganisation?.id === item.id;

  return (
    <TouchableOpacity
      style={[
        globalStyles.list.card,
        isActive && globalStyles.list.activeCard,
      ]}
      activeOpacity={0.7}
      onPress={onPickOrganisation}
    >
      <View style={globalStyles.list.container}>
        <View style={globalStyles.list.avatarContainer}>
          {!imageError && item.logo?.original ? (
            <Image
              source={{ uri: item.logo.original }}
              style={globalStyles.list.avatar}
              onError={handleImageError}
            />
          ) : (
            <View style={globalStyles.list.fallbackAvatar}>
              <Text style={globalStyles.list.fallbackText}>
                {item.name?.charAt(0) || '?'}
              </Text>
            </View>
          )}
        </View>
        <View style={globalStyles.list.textContainer}>
          <Text style={globalStyles.list.title}>{item.name}</Text>
        </View>
        {isActive && (
          <View style={globalStyles.list.activeIndicator}>
             <FontAwesomeIcon icon={faCheckCircle} color='green'/>
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
};

export default function OrganisationScreens() {
  const { userData, organisation } = useContext(AuthContext);

  return (
    <View style={globalStyles.container}>
      <FlatList
        data={userData.authorised_organisations}
        keyExtractor={(item) => item.id.toString()}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => (
          <GroupItem item={item} selectedOrganisation={organisation} />
        )}
      />
    </View>
  );
}

