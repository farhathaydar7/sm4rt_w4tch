import axios from "axios";

// Create a dedicated instance for API calls
const api = axios.create({
  baseURL: "/api", // Using a relative URL that will be proxied by Vite
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  withCredentials: true, // Important for cookies if using cookie auth
  timeout: 60000, // Increase timeout to 60 seconds for AI operations
});

// Modified post method to accept AbortController signal
api.postWithSignal = function (url, data = {}, options = {}) {
  const { signal, ...otherOptions } = options;

  return this.post(url, data, {
    ...otherOptions,
    signal: signal,
  });
};

// For development debugging
const isDevelopment =
  window.location.hostname === "localhost" ||
  window.location.hostname === "127.0.0.1";

// Helper function to debug token issues
const debugTokenProblem = (token) => {
  if (!token) {
    console.error("AUTH ERROR: No token found in localStorage");
    return;
  }

  // Check token format
  const parts = token.split(".");
  if (parts.length !== 3) {
    console.error(
      "AUTH ERROR: Token format is invalid. JWT should have 3 parts separated by dots."
    );
    console.error(`Token has ${parts.length} parts instead of 3`);
    return;
  }

  try {
    // Try to decode header and payload parts (not the signature)
    const header = JSON.parse(atob(parts[0]));
    const payload = JSON.parse(atob(parts[1]));

    console.log("JWT Header:", header);
    console.log("JWT Payload:", payload);

    // Check for common issues
    const now = Math.floor(Date.now() / 1000);
    if (payload.exp && payload.exp < now) {
      console.error(
        `AUTH ERROR: Token expired at ${new Date(
          payload.exp * 1000
        ).toLocaleString()}`
      );
    }

    if (payload.nbf && payload.nbf > now) {
      console.error(
        `AUTH ERROR: Token not valid before ${new Date(
          payload.nbf * 1000
        ).toLocaleString()}`
      );
    }
  } catch (e) {
    console.error(
      "AUTH ERROR: Could not decode token parts. The token might be malformed:",
      e
    );
  }
};

// Add a request interceptor to add the auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("token");

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // Log requests in development
    if (isDevelopment) {
      console.log("API Request:", {
        url: config.url,
        method: config.method,
        headers: {
          ...config.headers,
          Authorization: config.headers.Authorization
            ? "Bearer [HIDDEN]"
            : undefined,
        },
        data: config.data,
      });
    }

    return config;
  },
  (error) => {
    console.error("API Request Error:", error);
    return Promise.reject(error);
  }
);

// Add a response interceptor to handle errors
api.interceptors.response.use(
  (response) => {
    // Log responses in development
    if (isDevelopment) {
      console.log("API Response:", {
        url: response.config.url,
        status: response.status,
        statusText: response.statusText,
        data: response.data,
      });
    }

    return response;
  },
  async (error) => {
    // Log errors in development
    if (isDevelopment) {
      console.error("API Error:", {
        message: error.message,
        response: error.response
          ? {
              url: error.config.url,
              status: error.response.status,
              statusText: error.response.statusText,
              data: error.response.data,
            }
          : "No response",
      });
    }

    // For auth endpoints, just pass through the error
    if (error.config.url.includes("/auth/")) {
      return Promise.reject(error);
    }

    // If the error is 401, print detailed token debugging and redirect to login
    if (error.response?.status === 401) {
      console.error("=====================================================");
      console.error("AUTH ERROR: 401 Unauthorized - Token validation failed");
      console.error("=====================================================");

      // Debug the token
      const token = localStorage.getItem("token");
      debugTokenProblem(token);

      // For auth failures, just redirect to login
      localStorage.removeItem("token");
      window.location.href = "/login";
      return Promise.reject(error);
    }

    // For other errors
    return Promise.reject(error);
  }
);

// Auth endpoints
export const auth = {
  login: (credentials) => api.post("/auth/login", credentials),
  register: (userData) => api.post("/auth/register", userData),
  logout: () => api.post("/auth/logout"),
  user: () => api.get("/auth/me"),
  // Create a specific login function that stores the token
  authenticateUser: async (credentials) => {
    try {
      const response = await api.post("/auth/login", credentials);
      if (
        response.data &&
        response.data.authorization &&
        response.data.authorization.token
      ) {
        // Store the token properly - make sure we're getting the right structure
        const token = response.data.authorization.token;
        console.log("Setting token with length:", token.length);
        localStorage.setItem("token", token);
      } else {
        console.error("Login response doesn't contain token:", response.data);
      }
      return response;
    } catch (error) {
      console.error("Authentication error:", error);
      throw error;
    }
  },
};

// Activity metrics endpoints
export const activityMetrics = {
  daily: () => api.get("/activity-metrics/daily"),
  weekly: () => api.get("/activity-metrics/weekly"),
  monthly: () => api.get("/activity-metrics/monthly"),
  getAll: () => api.get("/activity-metrics"),
  getById: (id) => api.get(`/activity-metrics/${id}`),
  getByCsvUpload: (csvUploadId) =>
    api.get(`/csv-uploads/${csvUploadId}/activity-metrics`),
};

export default api;
