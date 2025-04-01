import React, { useEffect, useRef } from "react";
import { Provider, useDispatch } from "react-redux";
import {
  BrowserRouter as Router,
  Routes,
  Route,
  Navigate,
} from "react-router-dom";
import { store } from "./store";
import Login from "./pages/Login";
import Register from "./pages/Register";
import Dashboard from "./pages/Dashboard";
import ProtectedRoute from "./components/ProtectedRoute";
import { checkAuth } from "./store/slices/authSlice";

// Component to initialize authentication check
const AppInit = ({ children }) => {
  const dispatch = useDispatch();
  // Use a ref to track if auth check has been attempted to prevent infinite loops
  const authCheckAttempted = useRef(false);

  useEffect(() => {
    // Only check auth once - prevents infinite loop
    if (!authCheckAttempted.current && localStorage.getItem("token")) {
      authCheckAttempted.current = true;
      dispatch(checkAuth()).catch((err) => {
        console.error("Auth check failed:", err);
        // If auth check fails, clear token
        localStorage.removeItem("token");
      });
    }
  }, [dispatch]);

  return <>{children}</>;
};

const App = () => {
  return (
    <Provider store={store}>
      <Router>
        <AppInit>
          <div className="min-h-screen bg-gray-100">
            <Routes>
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route
                path="/"
                element={
                  <ProtectedRoute>
                    <Dashboard />
                  </ProtectedRoute>
                }
              />
              <Route path="*" element={<Navigate to="/" />} />
            </Routes>
          </div>
        </AppInit>
      </Router>
    </Provider>
  );
};

export default App;
