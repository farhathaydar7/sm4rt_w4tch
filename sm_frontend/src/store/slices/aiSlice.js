import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import api from "../../services/api";

// Async thunks
export const testAIConnection = createAsyncThunk(
  "ai/testConnection",
  async (_, { rejectWithValue }) => {
    try {
      const response = await api.get("/ai/test");
      return response.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Failed to connect to AI service" }
      );
    }
  }
);

export const getAIPredictions = createAsyncThunk(
  "ai/getPredictions",
  async (data = {}, { rejectWithValue }) => {
    try {
      const response = await api.post("/ai/predict", { data });
      return response.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Failed to get AI predictions" }
      );
    }
  }
);

export const getAIInsights = createAsyncThunk(
  "ai/getInsights",
  async (data = {}, { rejectWithValue, signal }) => {
    // Create an AbortController that will be cancelled if Redux cancels this thunk
    const abortController = new AbortController();

    // If the thunk is aborted, abort our fetch as well
    signal.addEventListener("abort", () => {
      abortController.abort();
    });

    try {
      // Set a timeout for the request (45 seconds)
      const timeoutId = setTimeout(() => {
        abortController.abort();
      }, 45000);

      const response = await api.postWithSignal(
        "/ai/insights",
        { data },
        { signal: abortController.signal }
      );

      // Clear the timeout if the request completes successfully
      clearTimeout(timeoutId);

      return response.data;
    } catch (error) {
      console.log("AI Insights error:", error.name, error.message);

      // Handle abort errors specifically
      if (
        error.name === "AbortError" ||
        error.name === "CanceledError" ||
        error.message?.includes("abort") ||
        error.message?.includes("cancel")
      ) {
        return rejectWithValue({
          message:
            "Request was cancelled. The AI service may be taking too long to respond.",
        });
      }

      // Handle network errors
      if (!navigator.onLine) {
        return rejectWithValue({
          message:
            "You appear to be offline. Please check your internet connection.",
        });
      }

      return rejectWithValue(
        error.response?.data || {
          message: `Failed to get AI insights: ${error.message}`,
        }
      );
    }
  }
);

const initialState = {
  isConnected: false,
  connectionStatus: null,
  predictions: null,
  insights: null,
  loading: false,
  error: null,
  isFallback: false,
};

const aiSlice = createSlice({
  name: "ai",
  initialState,
  reducers: {
    clearAIError: (state) => {
      state.error = null;
    },
    resetAIState: (state) => {
      state.predictions = null;
      state.insights = null;
      state.error = null;
      state.isFallback = false;
    },
  },
  extraReducers: (builder) => {
    builder
      // Test AI Connection
      .addCase(testAIConnection.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(testAIConnection.fulfilled, (state, action) => {
        state.loading = false;
        state.isConnected = action.payload.status === "success";
        state.connectionStatus = action.payload;
      })
      .addCase(testAIConnection.rejected, (state, action) => {
        state.loading = false;
        state.isConnected = false;
        state.error =
          action.payload?.message || "Failed to connect to AI service";
      })

      // Get AI Predictions
      .addCase(getAIPredictions.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(getAIPredictions.fulfilled, (state, action) => {
        state.loading = false;
        state.predictions = action.payload.data.predictions;
        state.isFallback = action.payload.data.is_fallback || false;
        if (action.payload.data.message) {
          state.error = action.payload.data.message;
        } else {
          state.error = null;
        }
      })
      .addCase(getAIPredictions.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload?.message || "Failed to get AI predictions";
      })

      // Get AI Insights
      .addCase(getAIInsights.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(getAIInsights.fulfilled, (state, action) => {
        state.loading = false;
        state.insights = action.payload.data.insights;
        state.isFallback = action.payload.data.is_fallback || false;
        if (action.payload.data.message) {
          state.error = action.payload.data.message;
        } else {
          state.error = null;
        }
      })
      .addCase(getAIInsights.rejected, (state, action) => {
        state.loading = false;
        state.error = action.payload?.message || "Failed to get AI insights";
      });
  },
});

export const { clearAIError, resetAIState } = aiSlice.actions;
export default aiSlice.reducer;
