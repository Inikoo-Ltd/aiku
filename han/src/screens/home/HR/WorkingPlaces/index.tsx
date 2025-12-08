import { StyleSheet, View } from "react-native";
import BaseList from "~/components/Base/BaseList";
import { COLORS, ROUTES } from "~/constants";
import { Card, IconButton , Avatar} from "react-native-paper";
import React from "react";
import { useNavigation } from "@react-navigation/native";

const WorkingPlaces = () => {
  const navigation = useNavigation()

  const cardContent = (data : object) => {
    const handleEdit = () => {
      navigation.navigate(`${ROUTES.WORKING_PLACES} Detail`, { id: data.id });
    };
  
    return (
      <Card.Title
      title={data.name}
      subtitle={data.type}
      left={(props) => <Avatar.Icon {...props} icon="map-marker"/>}
      right={(props) => <IconButton {...props} icon="chevron-right" onPress={handleEdit} />}
      />
    );
  };

  return (
    <View style={styles.container}>
      <BaseList
        urlKey="hr-working-places"
        urlPrefix={ROUTES.WORKING_PLACES}
        cardContent={cardContent}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    paddingHorizontal: 10,
    paddingVertical: 0,
  },
  titleContainer: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 10,
  },
  leftContainer: {
    flexDirection: "row",
  },
  titleText: {
    marginLeft: 10,
  },
  title: {
    fontSize: 18,
    fontWeight: "bold",
    color: COLORS.primary,
  },
  description: {
    fontSize: 14,
    color: "gray",
    marginTop: 5,
  },
  chip: {
    padding: 3,
  },
  cardContent: {
    paddingVertical: 8, 
    paddingHorizontal: 12, 
  },
  textContainer: {
    marginLeft: 10,
    flex: 1,
  },
});

export default WorkingPlaces;
