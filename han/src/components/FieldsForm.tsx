import React from "react";
import { Text, View, StyleSheet } from "react-native";
import { TextInput } from "react-native-paper";

export default function FormWorkingSpace(p : object) {
  const renderFieldsForm = () => {
    switch (p.type) {
      case 'text':
        return (
          <TextInput
            style={styles.TextInput}
            {...p}
          />
        );
        case 'textArea':
          return (
            <TextInput
              style={styles.TextArea}
              {...p}
            />
          );
      default:
        return null;
    }
  };

  return (
    <View>
      {renderFieldsForm()}
    </View>
  );
}

const styles = StyleSheet.create({
  TextInput :{
    fontSize :12,
    height : 40
  },
  TextArea : {
    fontSize :12,
    height : 80
  }
});