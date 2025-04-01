import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import api from "../../services/api";

// Async thunks
export const fetchAllActivities = createAsyncThunk(
  "activity/fetchAll",
  async (_, { rejectWithValue }) => {
    try {
      const response = await api.get("/activity/all");
      return response.data.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Failed to fetch activities" }
      );
    }
  }
);

export const fetchActivityByDate = createAsyncThunk(
  "activity/fetchByDate",
  async (date, { rejectWithValue }) => {
    try {
      const response = await api.get(`/activity/date/${date}`);
      return response.data.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Failed to fetch activity for date" }
      );
    }
  }
);

export const fetchWeeklySummary = createAsyncThunk(
  "activity/fetchWeeklySummary",
  async (weekId, { rejectWithValue }) => {
    try {
      const url = weekId ? `/activity/week/${weekId}` : "/activity/week";
      const response = await api.get(url);
      return response.data.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Failed to fetch weekly summary" }
      );
    }
  }
);

export const fetchActivityStats = createAsyncThunk(
  "activity/fetchStats",
  async (_, { rejectWithValue }) => {
    try {
      const response = await api.get("/activity/stats");
      return response.data.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Failed to fetch activity stats" }
      );
    }
  }
);

const initialState = {
  activities: [],
  currentDateActivity: null,
  weeklySummary: null,
  stats: null,
  loading: false,
  error: null,
};

const activitySlice = createSlice({
  name: "activity",
  initialState,
  reducers: {
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch All Activities
      .addCase(fetchAllActivities.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchAllActivities.fulfilled, (state, action) => {
        state.loading = false;
        state.activities = action.payload;
      })
      .addCase(fetchAllActivities.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload?.message || "Failed to fetch activities";
      })
      // Fetch Activity By Date
      .addCase(fetchActivityByDate.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchActivityByDate.fulfilled, (state, action) => {
        state.loading = false;
        state.currentDateActivity = action.payload;
      })
      .addCase(fetchActivityByDate.rejected, (state, action) => {
        state.loading = false;
        state.error =
          action.payload?.message || "Failed to fetch activity for date";
      })
      // Fetch Weekly Summary
      .addCase(fetchWeeklySummary.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchWeeklySummary.fulfilled, (state, action) => {
        state.loading = false;
        state.weeklySummary = action.payload;
      })
      .addCase(fetchWeeklySummary.rejected, (state, action) => {
        state.loading = false;
        state.error =
          action.payload?.message || "Failed to fetch weekly summary";
      })
      // Fetch Stats
      .addCase(fetchActivityStats.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchActivityStats.fulfilled, (state, action) => {
        state.loading = false;
        state.stats = action.payload;
      })
      .addCase(fetchActivityStats.rejected, (state, action) => {
        state.loading = false;
        state.error =
          action.payload?.message || "Failed to fetch activity stats";
      });
  },
});

export const { clearError } = activitySlice.actions;
export default activitySlice.reducer;
