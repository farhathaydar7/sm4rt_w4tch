import { configureStore } from "@reduxjs/toolkit";
import activityReducer from "./slices/activitySlice";

export const store = configureStore({
  reducer: {
    activity: activityReducer,
  },
});
