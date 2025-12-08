import { createStore, combineReducers } from "redux";
import thunk from "redux-thunk";
import Reducers from "./Reducers";

export default createStore(
  combineReducers(Reducers),
  {},
  thunk
);