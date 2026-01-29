import { dashboardApi } from '@/lib/api';
import { usePolling } from './usePolling';

export function useStats() {
  return usePolling(
    () => dashboardApi.getStats(),
    window.mqttBroadcast.refreshInterval
  );
}

export function useBrokers() {
  return usePolling(
    () => dashboardApi.getBrokers(),
    window.mqttBroadcast.refreshInterval
  );
}

export function useMessages(params?: {
  broker?: string;
  topic?: string;
  limit?: number;
}) {
  return usePolling(
    () => dashboardApi.getMessages(params),
    window.mqttBroadcast.refreshInterval,
    window.mqttBroadcast.loggingEnabled
  );
}

export function useThroughput(period: 'hour' | 'day' | 'week' = 'hour') {
  return usePolling(
    () => dashboardApi.getThroughput(period),
    window.mqttBroadcast.refreshInterval
  );
}
