import React, { memo } from "react";
import { View, StyleSheet, TouchableOpacity } from "react-native";
import { Icon, Chip, Text } from "react-native-paper";
import { noop } from 'lodash-es';

function Header(props) {

  const renderLeftArea = () => {
    if (!props.leftArea) {
      return (
        <>
          {/* <Text>{props.title}</Text> */}
          {props.record && props.record.count !== undefined && (
            <Chip>Records: {props.record.count}</Chip>
          )}
        </>
      );
    } else {
      return props.leftArea();
    }
  };

  const renderRightArea = () => {
    if (!props.rightArea) {
      return (
        <>
          <TouchableOpacity
            style={styles.iconArea}
            onPress={() => console.log("Search icon pressed")}
          >
            <Icon source="magnify" size={20} />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.iconArea}
            onPress={() => console.log("Filter icon pressed")}
          >
            <Icon source="sort" size={20} />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.iconArea}
            onPress={() => console.log("Sort icon pressed")}
          >
            <Icon source="filter" size={20} />
          </TouchableOpacity>
        </>
      );
    } else {
      return props.rightArea();
    }
  };

  return (
    <View style={styles.header}>
      <View style={styles.titleContainer}>{renderLeftArea()}</View>
      <View style={styles.iconContainer}>{renderRightArea()}</View>
    </View>
  );
}

Header.defaultProps = {
  title: "",
  record: {
    color: null,
    count: 0,
  },
};

export default (Header);

const styles = StyleSheet.create({
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingTop: 10,
    paddingBottom: 8,
  },
  iconContainer: {
    flexDirection: "row",
  },
  iconArea: {
    marginHorizontal: 5,
  },
});