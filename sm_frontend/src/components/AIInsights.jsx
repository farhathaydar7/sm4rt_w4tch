import React, { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { getAIInsights } from "../store/slices/aiSlice";
import styles from "./AIInsights.module.css";

const AIInsights = ({ activityMetrics }) => {
  const dispatch = useDispatch();
  const { insights, loading, error, isFallback } = useSelector(
    (state) => state.ai
  );

  useEffect(() => {
    if (activityMetrics) {
      const data = {
        activity_metrics: activityMetrics,
      };

      dispatch(getAIInsights(data));
    }
  }, [dispatch, activityMetrics]);

  if (loading) {
    return (
      <div className={styles.insightsCard}>
        <h3>Health Insights</h3>
        <div className={styles.loadingSpinner}>
          <p>Analyzing your health data...</p>
          <p>This may take a moment</p>
        </div>
      </div>
    );
  }

  if (error && !insights) {
    return (
      <div className={styles.insightsCard}>
        <h3>Health Insights</h3>
        <div className={styles.error}>
          <p>Unable to load insights: {error}</p>
          <button
            onClick={() => {
              dispatch(getAIInsights({ activity_metrics: activityMetrics }));
            }}
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!insights) {
    return (
      <div className={styles.insightsCard}>
        <h3>Health Insights</h3>
        <p>
          No insights available. Please ensure you have recent activity data.
        </p>
      </div>
    );
  }

  return (
    <div className={styles.insightsCard}>
      <h3>Health Insights</h3>
      {isFallback && (
        <div className={styles.fallbackNotice}>
          <p>
            <strong>Note:</strong> Using simplified analysis mode. The AI
            service is currently unavailable.
          </p>
          <p className={styles.fallbackInfo}>
            For more detailed insights, please ensure the AI service is running.
            <a
              href="/test_ai_connection.php"
              target="_blank"
              rel="noopener noreferrer"
              className={styles.testLink}
            >
              Test AI Connection
            </a>
          </p>
        </div>
      )}

      <div className={styles.insightSection}>
        <h4>Summary</h4>
        {insights.summary && (
          <p className={styles.summary}>{insights.summary}</p>
        )}
      </div>

      <div className={styles.insightSection}>
        <h4>Health Impact</h4>
        {insights.health_impact &&
          (Array.isArray(insights.health_impact) ? (
            <ul className={styles.impactList}>
              {insights.health_impact.map((impact, index) => (
                <li key={index}>{impact}</li>
              ))}
            </ul>
          ) : (
            <p>{insights.health_impact}</p>
          ))}
      </div>

      <div className={styles.insightSection}>
        <h4>Recommendations</h4>
        {insights.recommendations &&
          (Array.isArray(insights.recommendations) ? (
            <ul className={styles.recommendationsList}>
              {insights.recommendations.map((recommendation, index) => (
                <li key={index}>{recommendation}</li>
              ))}
            </ul>
          ) : (
            <p>{insights.recommendations}</p>
          ))}
      </div>

      <div className={styles.insightSection}>
        <h4>Next Steps</h4>
        {insights.next_steps &&
          (Array.isArray(insights.next_steps) ? (
            <ul className={styles.stepsList}>
              {insights.next_steps.map((step, index) => (
                <li key={index} className={styles.stepItem}>
                  <div className={styles.stepNumber}>{index + 1}</div>
                  <div className={styles.stepText}>{step}</div>
                </li>
              ))}
            </ul>
          ) : (
            <p>{insights.next_steps}</p>
          ))}
      </div>
    </div>
  );
};

export default AIInsights;
