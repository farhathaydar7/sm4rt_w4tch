import React from "react";
import { Provider } from "react-redux";
import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import { store } from "./store";
import ActivityDashboard from "./pages/ActivityDashboard";

const App = () => {
  return (
    <Provider store={store}>
      <Router>
        <div className="min-h-screen bg-gray-100">
          <Routes>
            <Route path="/" element={<ActivityDashboard />} />
          </Routes>
        </div>
      </Router>
    </Provider>
  );
};

export default App;
