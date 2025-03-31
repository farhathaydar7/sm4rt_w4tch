import axios from "axios";

// Create a dedicated instance for API calls
const api = axios.create({
  baseURL: "http://localhost:8000/api",
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// For development debugging
const isDevelopment =
  window.location.hostname === "localhost" ||
  window.location.hostname === "127.0.0.1";

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
  (error) => {
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

    if (error.response?.status === 401) {
      // Handle unauthorized access
      localStorage.removeItem("token");
      window.location.href = "/login";
    }

    return Promise.reject(error);
  }
);

// Auth endpoints
export const auth = {
  login: (credentials) => api.post("/auth/login", credentials),
  register: (userData) => api.post("/auth/register", userData),
  logout: () => api.post("/auth/logout"),
  user: () => api.get("/auth/me"),
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
