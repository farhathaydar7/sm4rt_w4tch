import React, { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { getAIPredictions } from "../store/slices/aiSlice";
import styles from "./AIPredictions.module.css";

const AIPredictions = ({ activityHistory, goals }) => {
  const dispatch = useDispatch();
  const { predictions, loading, error, isFallback } = useSelector(
    (state) => state.ai
  );

  useEffect(() => {
    if (activityHistory && activityHistory.length > 0) {
      const data = {
        activity_history: activityHistory,
        goals: goals || { daily_steps: 10000, weekly_active_minutes: 150 },
      };

      dispatch(getAIPredictions(data));
    }
  }, [dispatch, activityHistory, goals]);

  if (loading) {
    return (
      <div className={styles.predictionsCard}>
        <h3>AI Predictions</h3>
        <div className={styles.loadingSpinner}>Loading predictions...</div>
      </div>
    );
  }

  if (error && !predictions) {
    return (
      <div className={styles.predictionsCard}>
        <h3>AI Predictions</h3>
        <div className={styles.error}>
          <p>Unable to load predictions: {error}</p>
          <button
            onClick={() => {
              const data = {
                activity_history: activityHistory,
                goals: goals || {
                  daily_steps: 10000,
                  weekly_active_minutes: 150,
                },
              };
              dispatch(getAIPredictions(data));
            }}
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!predictions) {
    return (
      <div className={styles.predictionsCard}>
        <h3>AI Predictions</h3>
        <p>
          No predictions available. Please ensure you have sufficient activity
          data.
        </p>
      </div>
    );
  }

  return (
    <div className={styles.predictionsCard}>
      <h3>AI Predictions</h3>
      {isFallback && (
        <div className={styles.fallbackNotice}>
          Using fallback predictions due to AI service unavailability.
        </div>
      )}

      <div className={styles.predictionSection}>
        <h4>Goal Achievement</h4>
        {predictions.goal_achievement && (
          <div className={styles.goalPredictions}>
            <div className={styles.goalItem}>
              <span className={styles.goalLabel}>Daily Step Goal:</span>
              <span
                className={`${styles.likelihood} ${
                  predictions.goal_achievement.step_goal_likelihood === "high"
                    ? styles.likelihoodHigh
                    : predictions.goal_achievement.step_goal_likelihood ===
                      "moderate"
                    ? styles.likelihoodModerate
                    : styles.likelihoodLow
                }`}
              >
                {predictions.goal_achievement.step_goal_likelihood}
              </span>
              <span className={styles.goalTarget}>
                {predictions.goal_achievement.daily_step_goal} steps
              </span>
            </div>

            <div className={styles.goalItem}>
              <span className={styles.goalLabel}>
                Weekly Active Minutes Goal:
              </span>
              <span
                className={`${styles.likelihood} ${
                  predictions.goal_achievement
                    .active_minutes_goal_likelihood === "high"
                    ? styles.likelihoodHigh
                    : predictions.goal_achievement
                        .active_minutes_goal_likelihood === "moderate"
                    ? styles.likelihoodModerate
                    : styles.likelihoodLow
                }`}
              >
                {predictions.goal_achievement.active_minutes_goal_likelihood}
              </span>
              <span className={styles.goalTarget}>
                {predictions.goal_achievement.weekly_active_minutes_goal}{" "}
                minutes
              </span>
            </div>
          </div>
        )}
      </div>

      <div className={styles.predictionSection}>
        <h4>Activity Anomalies</h4>
        {predictions.anomaly_detection &&
          predictions.anomaly_detection.anomalies &&
          (predictions.anomaly_detection.anomalies.length > 0 ? (
            <ul className={styles.anomalyList}>
              {predictions.anomaly_detection.anomalies.map((anomaly, index) => (
                <li key={index} className={styles.anomalyItem}>
                  <span className={styles.anomalyDate}>{anomaly.date}:</span>
                  <span className={styles.anomalySteps}>
                    {anomaly.steps} steps
                  </span>
                  <span className={styles.anomalyReason}>
                    ({anomaly.reason})
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p>No significant anomalies detected in your activity pattern.</p>
          ))}
      </div>

      <div className={styles.predictionSection}>
        <h4>Future Projections</h4>
        {predictions.future_projections &&
          predictions.future_projections.length > 0 && (
            <div className={styles.projectionsTable}>
              <div className={styles.tableHeader}>
                <span>Date</span>
                <span>Day</span>
                <span>Steps</span>
                <span>Active Min</span>
              </div>
              {predictions.future_projections.map((projection, index) => (
                <div key={index} className={styles.projectionRow}>
                  <span>{projection.date}</span>
                  <span>{projection.day_of_week}</span>
                  <span>{projection.projected_steps}</span>
                  <span>{projection.projected_active_minutes}</span>
                </div>
              ))}
            </div>
          )}
      </div>

      <div className={styles.predictionSection}>
        <h4>Actionable Insights</h4>
        {predictions.actionable_insights &&
          (Array.isArray(predictions.actionable_insights) ? (
            <ul className={styles.insightsList}>
              {predictions.actionable_insights.map((insight, index) => (
                <li key={index}>{insight}</li>
              ))}
            </ul>
          ) : (
            <p>{predictions.actionable_insights}</p>
          ))}
      </div>
    </div>
  );
};

export default AIPredictions;
