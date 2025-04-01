import React, { useEffect, useState, useRef } from "react";
import { useDispatch, useSelector } from "react-redux";
import { getAIInsights } from "../store/slices/aiSlice";
import styles from "./AIInsights.module.css";

const AIInsights = ({ activityMetrics }) => {
  const dispatch = useDispatch();
  const { insights, loading, error, isFallback } = useSelector(
    (state) => state.ai
  );
  const [hasFetched, setHasFetched] = useState(false);
  const [retryCount, setRetryCount] = useState(0);
  const [requestId, setRequestId] = useState(Date.now().toString());
  const abortControllerRef = useRef(null);
  const [parsedRawInsights, setParsedRawInsights] = useState(null);

  // Function to format metrics in the expected structure
  const formatMetrics = (rawMetrics) => {
    // Extract active_minutes from records if available
    let activeMinutes = 0;
    if (rawMetrics.records && rawMetrics.records.length > 0) {
      // Sum up active minutes from all records for the day
      activeMinutes = rawMetrics.records.reduce(
        (sum, record) => sum + (record.active_minutes || 0),
        0
      );
    }

    return {
      daily_steps: rawMetrics.total_steps || 0,
      active_minutes: activeMinutes,
      distance: rawMetrics.total_distance || 0,
    };
  };

  // Parse raw response if available
  useEffect(() => {
    if (insights && insights.raw_response) {
      try {
        // First try to extract JSON content between markdown code blocks
        const jsonMatch = insights.raw_response.match(
          /```json\n([\s\S]*?)\n```/
        );

        let jsonContent = "";
        if (jsonMatch && jsonMatch[1]) {
          jsonContent = jsonMatch[1];
        } else {
          // If no markdown blocks, try to find JSON-like content
          jsonContent = insights.raw_response;
        }

        // Remove any truncation indicators like "..." or ellipsis
        jsonContent = jsonContent.replace(/…/g, "...");

        // Try to fix common JSON syntax errors
        // 1. Find unclosed arrays or objects
        const openBraces = (jsonContent.match(/\{/g) || []).length;
        const closeBraces = (jsonContent.match(/\}/g) || []).length;
        const openBrackets = (jsonContent.match(/\[/g) || []).length;
        const closeBrackets = (jsonContent.match(/\]/g) || []).length;

        // Add missing closing braces/brackets
        if (openBraces > closeBraces) {
          jsonContent += "}".repeat(openBraces - closeBraces);
        }
        if (openBrackets > closeBrackets) {
          jsonContent += "]".repeat(openBrackets - closeBrackets);
        }

        // 2. Fix hanging string properties by making them empty strings
        jsonContent = jsonContent.replace(
          /\"([^\"]*?)\":(\s*?)(\n|,|}|\])/g,
          '"$1": ""$3'
        );

        // 3. Replace any trailing commas before closing brackets/braces
        jsonContent = jsonContent.replace(/,(\s*?)([\}\]])/g, "$1$2");

        // Try to parse the fixed JSON
        console.log("Attempting to parse fixed JSON:", jsonContent);
        const parsed = JSON.parse(jsonContent);
        setParsedRawInsights(parsed);
        console.log("Successfully parsed raw JSON from AI response");
      } catch (err) {
        console.error(
          "Error parsing raw JSON response:",
          err,
          insights.raw_response
        );

        // Fallback: try to extract useful parts even if the JSON is invalid
        try {
          // Extract main sections by looking for patterns
          const extractedData = {};

          // Extract summary section if present
          const summaryMatch = insights.raw_response.match(
            /"summary"\s*:\s*(\{[^}]*\})/
          );
          if (summaryMatch && summaryMatch[1]) {
            try {
              // Try to parse just this section
              extractedData.summary = JSON.parse(
                summaryMatch[1].replace(/…/g, "...")
              );
            } catch {
              // If parsing fails, use it as a string
              extractedData.summary = "Activity data analysis available";
            }
          }

          // Extract other sections similarly
          const sections = [
            "health_impact",
            "recommendations",
            "next_steps",
            "long_term_benefits",
          ];
          sections.forEach((section) => {
            const sectionMatch = insights.raw_response.match(
              new RegExp(`"${section}"\\s*:\\s*(\\[[^\\]]*\\])`)
            );
            if (sectionMatch && sectionMatch[1]) {
              try {
                extractedData[section] = JSON.parse(
                  sectionMatch[1].replace(/…/g, "...")
                );
              } catch {
                extractedData[section] = [
                  `${section.replace(/_/g, " ")} data available`,
                ];
              }
            }
          });

          // Use the extracted data if we found anything
          if (Object.keys(extractedData).length > 0) {
            setParsedRawInsights(extractedData);
            console.log("Created partial data from malformed JSON");
          } else {
            setParsedRawInsights(null);
          }
        } catch (fallbackErr) {
          console.error("Fallback extraction also failed:", fallbackErr);
          setParsedRawInsights(null);
        }
      }
    } else {
      setParsedRawInsights(null);
    }
  }, [insights]);

  // Cancel any existing requests before unmounting
  useEffect(() => {
    return () => {
      if (abortControllerRef.current) {
        console.log("Cancelling previous AI request on unmount");
        abortControllerRef.current.abort();
      }
    };
  }, []);

  // Function to handle fetching insights with retry capability
  const fetchInsights = () => {
    if (activityMetrics) {
      // Cancel any ongoing requests
      if (abortControllerRef.current) {
        console.log("Cancelling previous AI request before new request");
        abortControllerRef.current.abort();
      }

      const formattedMetrics = formatMetrics(activityMetrics);
      console.log("Sending formatted metrics to AI:", formattedMetrics);

      const data = {
        activity_metrics: formattedMetrics,
      };

      // Create a new request ID to force React to treat this as a new operation
      setRequestId(Date.now().toString());
      setHasFetched(true);

      // Use a new AbortController for this request
      abortControllerRef.current = new AbortController();

      dispatch(getAIInsights(data));
    }
  };

  // Initial fetch on component mount or when activity metrics change
  useEffect(() => {
    if (activityMetrics && !hasFetched) {
      fetchInsights();
    }
  }, [dispatch, activityMetrics, hasFetched]);

  // Reset fetch state when error occurs to allow retry
  useEffect(() => {
    if (error) {
      console.log("Error detected, enabling retry capability");
      setHasFetched(false);
    }
  }, [error]);

  // Handle retry with increasing delay for better stability
  const handleRetry = () => {
    setRetryCount((prev) => prev + 1);
    // Add a delay before retrying to let any pending requests complete
    const delay = Math.min(2000 * retryCount, 10000); // Increases delay with each retry, max 10 seconds

    console.log(`Scheduling retry with ${delay}ms delay`);
    setTimeout(() => {
      fetchInsights();
    }, delay);
  };

  // Get the effective insights data source (parsed raw response or regular insights)
  const getEffectiveInsights = () => {
    if (parsedRawInsights) {
      return parsedRawInsights;
    }
    return insights;
  };

  const renderSummarySection = (summary) => {
    // If summary is just a string, display it directly
    if (typeof summary === "string") {
      return <p className={styles.summary}>{summary}</p>;
    }

    // If it has the newer structure with current_activity
    if (summary.current_activity) {
      return (
        <div className={styles.summaryDetail}>
          <div className={styles.summaryMetrics}>
            <div className={styles.metricGroup}>
              <h5>Today's Activity</h5>
              <div className={styles.metricRow}>
                <div className={styles.metric}>
                  <span className={styles.metricLabel}>Steps</span>
                  <span className={styles.metricValue}>
                    {summary.current_activity.steps?.toLocaleString() || 0}
                  </span>
                </div>
                <div className={styles.metric}>
                  <span className={styles.metricLabel}>Active Minutes</span>
                  <span className={styles.metricValue}>
                    {summary.current_activity.active_minutes || 0}
                  </span>
                </div>
                <div className={styles.metric}>
                  <span className={styles.metricLabel}>Distance</span>
                  <span className={styles.metricValue}>
                    {summary.current_activity.distance || 0} km
                  </span>
                </div>
              </div>
            </div>

            <div className={styles.metricGroup}>
              <h5>Your 14-Day Average</h5>
              <div className={styles.metricRow}>
                <div className={styles.metric}>
                  <span className={styles.metricLabel}>Steps</span>
                  <span className={styles.metricValue}>
                    {summary.historical_context?.average_steps?.toLocaleString() ||
                      0}
                  </span>
                </div>
                <div className={styles.metric}>
                  <span className={styles.metricLabel}>Active Minutes</span>
                  <span className={styles.metricValue}>
                    {summary.historical_context?.average_active_minutes || 0}
                  </span>
                </div>
                <div className={styles.metric}>
                  <span className={styles.metricLabel}>Distance</span>
                  <span className={styles.metricValue}>
                    {summary.historical_context?.average_distance || 0} km
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div className={styles.benchmark}>
            <h5>Recommended Daily Targets</h5>
            <div className={styles.benchmarkRow}>
              <span>
                Steps:{" "}
                {summary.health_benchmarks?.recommended_daily_steps?.toLocaleString() ||
                  "10,000"}
              </span>
              <span>
                Active Minutes:{" "}
                {summary.health_benchmarks?.recommended_daily_active_minutes ||
                  "30"}
              </span>
              <span>
                Weekly Active Minutes:{" "}
                {summary.health_benchmarks?.weekly_target || "150"}
              </span>
            </div>
          </div>
        </div>
      );
    }

    // For older format or mixed formats
    return (
      <p className={styles.summary}>
        {summary.today_vs_average?.steps ||
          summary.today_vs_benchmark?.steps ||
          summary.comparison?.today_vs_daily_average ||
          "Activity analysis results available"}
      </p>
    );
  };

  // Find the recommendations section and update it to properly render the new format
  const renderRecommendations = (recommendations) => {
    if (!recommendations || recommendations.length === 0) {
      return <p>No recommendations available.</p>;
    }

    if (!Array.isArray(recommendations)) {
      return (
        <p>
          {typeof recommendations === "string"
            ? recommendations
            : JSON.stringify(recommendations)}
        </p>
      );
    }

    return (
      <ul className={styles.recommendationsList}>
        {recommendations.map((recommendation, index) => {
          // Handle different formats
          if (typeof recommendation === "string") {
            return <li key={index}>{recommendation}</li>;
          }

          // New format with title and description
          if (recommendation.title && recommendation.description) {
            return (
              <li key={index} className={styles.recommendationItem}>
                <h5 className={styles.recommendationTitle}>
                  {recommendation.title}
                </h5>
                <p className={styles.recommendationDesc}>
                  {recommendation.description}
                </p>
              </li>
            );
          }

          // Fallback for other formats
          return (
            <li key={index}>
              {recommendation.action ||
                recommendation.details ||
                JSON.stringify(recommendation)}
            </li>
          );
        })}
      </ul>
    );
  };

  if (loading) {
    return (
      <div className={styles.insightsCard} key={`loading-${requestId}`}>
        <h3>Health Insights</h3>
        <div className={styles.loadingSpinner}>
          <p>Analyzing your health data...</p>
          <p>This may take a moment (up to 45 seconds)</p>
        </div>
      </div>
    );
  }

  if (error && !insights) {
    return (
      <div className={styles.insightsCard} key={`error-${requestId}`}>
        <h3>Health Insights</h3>
        <div className={styles.error}>
          <p>Unable to load insights: {error}</p>
          {error.includes("cancelled") || error.includes("timed out") ? (
            <div className={styles.errorHelp}>
              <p>The AI service is taking longer than expected to respond.</p>
              <p>This could be because:</p>
              <ul>
                <li>The AI model is processing a large request</li>
                <li>The server is experiencing high load</li>
                <li>Your connection may have been interrupted</li>
              </ul>
            </div>
          ) : null}
          <button onClick={handleRetry}>Try Again</button>
        </div>
      </div>
    );
  }

  if (!insights && !parsedRawInsights) {
    return (
      <div className={styles.insightsCard} key={`empty-${requestId}`}>
        <h3>Health Insights</h3>
        <p>
          No insights available. Please ensure you have recent activity data.
        </p>
      </div>
    );
  }

  // Use either the parsed raw response or the regular insights
  const effectiveInsights = getEffectiveInsights();

  return (
    <div className={styles.insightsCard} key={`insights-${requestId}`}>
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
              href="/sm4rt_w4tch/sm_server/public/ai_connection_test.php"
              target="_blank"
              rel="noopener noreferrer"
              className={styles.testLink}
            >
              Test AI Connection
            </a>
            <button onClick={handleRetry} className={styles.refreshButton}>
              Retry AI Analysis
            </button>
          </p>
        </div>
      )}

      {parsedRawInsights && (
        <div className={styles.useRawNotice}>
          <p>
            <strong>Note:</strong> Using directly parsed AI response data for
            enhanced insights.
          </p>
        </div>
      )}

      <div className={styles.insightSection}>
        <h4>Summary</h4>
        {effectiveInsights.summary &&
          renderSummarySection(effectiveInsights.summary)}
      </div>

      <div className={styles.insightSection}>
        <h4>Health Impact</h4>
        {effectiveInsights.health_impact &&
          (Array.isArray(effectiveInsights.health_impact) ? (
            <ul className={styles.impactList}>
              {effectiveInsights.health_impact.map((impact, index) => (
                <li key={index}>
                  {typeof impact === "string"
                    ? impact
                    : impact.point || impact.evidence || JSON.stringify(impact)}
                </li>
              ))}
            </ul>
          ) : (
            <p>
              {typeof effectiveInsights.health_impact === "string"
                ? effectiveInsights.health_impact
                : JSON.stringify(effectiveInsights.health_impact)}
            </p>
          ))}
      </div>

      <div className={styles.insightSection}>
        <h4>Recommendations</h4>
        {effectiveInsights.recommendations &&
          renderRecommendations(effectiveInsights.recommendations)}
      </div>

      <div className={styles.insightSection}>
        <h4>Next Steps</h4>
        {effectiveInsights.next_steps &&
          (Array.isArray(effectiveInsights.next_steps) ? (
            <ul className={styles.stepsList}>
              {effectiveInsights.next_steps.map((step, index) => (
                <li key={index} className={styles.stepItem}>
                  <div className={styles.stepNumber}>{index + 1}</div>
                  <div className={styles.stepText}>
                    {typeof step === "string"
                      ? step
                      : step.action && step.metric
                      ? `${step.action} ${step.metric}`
                      : step.action || step.metric || JSON.stringify(step)}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p>
              {typeof effectiveInsights.next_steps === "string"
                ? effectiveInsights.next_steps
                : JSON.stringify(effectiveInsights.next_steps)}
            </p>
          ))}
      </div>

      {effectiveInsights.long_term_benefits && (
        <div className={styles.insightSection}>
          <h4>Long-term Benefits</h4>
          {Array.isArray(effectiveInsights.long_term_benefits) ? (
            <ul className={styles.benefitsList}>
              {effectiveInsights.long_term_benefits.map((benefit, index) => (
                <li key={index}>
                  {typeof benefit === "string"
                    ? benefit
                    : benefit.benefit ||
                      benefit.rationale ||
                      JSON.stringify(benefit)}
                </li>
              ))}
            </ul>
          ) : (
            <p className={styles.longTermBenefits}>
              {typeof effectiveInsights.long_term_benefits === "string"
                ? effectiveInsights.long_term_benefits
                : JSON.stringify(effectiveInsights.long_term_benefits)}
            </p>
          )}
        </div>
      )}
    </div>
  );
};

export default AIInsights;
