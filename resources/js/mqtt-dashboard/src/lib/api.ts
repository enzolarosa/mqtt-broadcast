import axios from 'axios';
import type {
  DashboardStats,
  Broker,
  MessageLog,
  Topic,
  ThroughputData,
  MetricsSummary,
} from '@/types';

// Get config from window (injected in blade template)
declare global {
  interface Window {
    mqttBroadcast: {
      apiUrl: string;
      loggingEnabled: boolean;
      refreshInterval: number;
    };
  }
}

const api = axios.create({
  baseURL: window.mqttBroadcast.apiUrl,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

export const dashboardApi = {
  getStats: async (): Promise<DashboardStats> => {
    const { data } = await api.get<{ data: DashboardStats }>('/stats');
    return data.data;
  },

  getBrokers: async (): Promise<Broker[]> => {
    const { data } = await api.get<{ data: Broker[] }>('/brokers');
    return data.data;
  },

  getBroker: async (id: number): Promise<Broker> => {
    const { data } = await api.get<{ data: Broker }>(`/brokers/${id}`);
    return data.data;
  },

  getMessages: async (params?: {
    broker?: string;
    topic?: string;
    limit?: number;
  }): Promise<MessageLog[]> => {
    const { data } = await api.get<{ data: MessageLog[] }>('/messages', {
      params,
    });
    return data.data;
  },

  getMessage: async (id: number): Promise<MessageLog> => {
    const { data } = await api.get<{ data: MessageLog }>(`/messages/${id}`);
    return data.data;
  },

  getTopics: async (): Promise<Topic[]> => {
    const { data } = await api.get<{ data: Topic[] }>('/topics');
    return data.data;
  },

  getThroughput: async (period: 'hour' | 'day' | 'week' = 'hour'): Promise<ThroughputData[]> => {
    const { data } = await api.get<{ data: ThroughputData[] }>(
      '/metrics/throughput',
      { params: { period } }
    );
    return data.data;
  },

  getMetricsSummary: async (): Promise<MetricsSummary | null> => {
    const { data } = await api.get<{ data: MetricsSummary | null }>(
      '/metrics/summary'
    );
    return data.data;
  },
};
