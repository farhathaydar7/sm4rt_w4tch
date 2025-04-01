import { useDispatch, useSelector } from "react-redux";
import {
  login,
  register,
  logout,
  clearError,
  checkAuth,
} from "../store/slices/authSlice";

export const useAuth = () => {
  const dispatch = useDispatch();
  const { user, isAuthenticated, loading, error } = useSelector(
    (state) => state.auth
  );

  const handleLogin = (credentials) => {
    return dispatch(login(credentials));
  };

  const handleRegister = (userData) => {
    return dispatch(register(userData));
  };

  const handleLogout = () => {
    return dispatch(logout());
  };

  const clearAuthError = () => {
    dispatch(clearError());
  };

  const verifyAuth = () => {
    return dispatch(checkAuth());
  };

  return {
    user,
    isAuthenticated,
    loading,
    error,
    login: handleLogin,
    register: handleRegister,
    logout: handleLogout,
    clearError: clearAuthError,
    checkAuth: verifyAuth,
  };
};
