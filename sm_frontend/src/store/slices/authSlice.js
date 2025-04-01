import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { auth } from "../../services/api";

export const login = createAsyncThunk(
  "auth/login",
  async (credentials, { rejectWithValue }) => {
    try {
      const response = await auth.authenticateUser(credentials);
      return response.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Login failed" }
      );
    }
  }
);

export const register = createAsyncThunk(
  "auth/register",
  async (userData, { rejectWithValue }) => {
    try {
      const response = await auth.register(userData);
      if (
        response.data &&
        response.data.authorization &&
        response.data.authorization.token
      ) {
        localStorage.setItem("token", response.data.authorization.token);
      }
      return response.data;
    } catch (error) {
      return rejectWithValue(
        error.response?.data || { message: "Registration failed" }
      );
    }
  }
);

export const logout = createAsyncThunk("auth/logout", async () => {
  try {
    await auth.logout();
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    localStorage.removeItem("token");
  }
});

export const checkAuth = createAsyncThunk(
  "auth/checkAuth",
  async (_, { rejectWithValue }) => {
    try {
      const token = localStorage.getItem("token");
      if (!token) {
        return rejectWithValue({ message: "No token found" });
      }

      const response = await auth.user();
      console.log("Auth check successful", response.data);
      return response.data;
    } catch (error) {
      console.error("Auth check failed:", error);
      // If authentication check fails, clear the token
      localStorage.removeItem("token");
      return rejectWithValue(
        error.response?.data || { message: "Authentication check failed" }
      );
    }
  }
);

const initialState = {
  user: null,
  token: localStorage.getItem("token"),
  isAuthenticated: !!localStorage.getItem("token"),
  loading: false,
  error: null,
};

const authSlice = createSlice({
  name: "auth",
  initialState,
  reducers: {
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(login.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(login.fulfilled, (state, action) => {
        state.loading = false;
        state.isAuthenticated = true;
        state.user = action.payload.user;

        // Make sure we're accessing the correct token structure
        if (
          action.payload.authorization &&
          action.payload.authorization.token
        ) {
          state.token = action.payload.authorization.token;
        } else {
          console.error(
            "Login response doesn't match expected structure:",
            action.payload
          );
        }
      })
      .addCase(login.rejected, (state, action) => {
        state.loading = false;
        state.isAuthenticated = false;
        state.error = action.payload?.message || "Login failed";
      })
      .addCase(register.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(register.fulfilled, (state, action) => {
        state.loading = false;
        state.isAuthenticated = true;
        state.user = action.payload.user;

        // Make sure we're accessing the correct token structure
        if (
          action.payload.authorization &&
          action.payload.authorization.token
        ) {
          state.token = action.payload.authorization.token;
        }
      })
      .addCase(register.rejected, (state, action) => {
        state.loading = false;
        state.isAuthenticated = false;
        state.error = action.payload?.message || "Registration failed";
      })
      .addCase(logout.fulfilled, (state) => {
        state.user = null;
        state.token = null;
        state.isAuthenticated = false;
      })
      .addCase(checkAuth.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(checkAuth.fulfilled, (state, action) => {
        state.loading = false;
        state.isAuthenticated = true;
        state.user = action.payload.user || action.payload.data;
      })
      .addCase(checkAuth.rejected, (state) => {
        state.loading = false;
        state.isAuthenticated = false;
        state.user = null;
        state.token = null;
      });
  },
});

export const { clearError } = authSlice.actions;
export default authSlice.reducer;
