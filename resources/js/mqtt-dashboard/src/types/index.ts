export interface DashboardStats {
  status: 'running' | 'stopped';
  brokers: {
    total: number;
    active: number;
    stale: number;
  };
  messages: {
    per_minute: number;
    last_hour: number;
    last_24h: number;
    logging_enabled: boolean;
  };
  queue: {
    pending: number;
    name: string;
  };
  memory: {
    current_mb: number;
    threshold_mb: number;
    usage_percent: number;
  };
  uptime_seconds: number;
}

export interface Broker {
  id: number;
  name: string;
  connection: string;
  pid: number;
  status: 'active' | 'stale';
  connection_status: 'connected' | 'idle' | 'reconnecting' | 'disconnected';
  working: boolean;
  started_at: string;
  last_heartbeat_at: string;
  last_message_at: string | null;
  uptime_seconds: number;
  uptime_human: string;
  messages_24h: number;
}

export interface MessageLog {
  id: number;
  broker: string;
  topic: string;
  message: string;
  message_preview: string;
  created_at: string;
  created_at_human: string;
}

export interface Topic {
  topic: string;
  count: number;
}

export interface ThroughputData {
  time: string;
  timestamp: string;
  count: number;
}

export interface MetricsSummary {
  last_hour: {
    total: number;
    per_minute: number;
  };
  last_24h: {
    total: number;
    per_hour: number;
  };
  last_7days: {
    total: number;
    per_day: number;
  };
  peak_minute: {
    time: string;
    count: number;
  };
}
