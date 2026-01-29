import { useState } from 'react';
import { StatsCard } from './StatsCard';
import { ThroughputChart } from './ThroughputChart';
import { BrokerTable } from './BrokerTable';
import { MessageLog } from './MessageLog';
import { ThemeToggle } from './ThemeToggle';
import { Navigation, TabType } from './Navigation';
import { DocsPage } from './DocsPage';
import { useStats } from '@/hooks/useDashboard';
import {
  Activity,
  Server,
  Zap,
  Database,
  Loader2,
  AlertCircle,
} from 'lucide-react';
import { Badge } from './ui/badge';

export function Dashboard() {
  const [activeTab, setActiveTab] = useState<TabType>('dashboard');
  const { data: stats, loading, error } = useStats();

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b bg-card">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-primary/10 rounded-lg">
                <Activity className="h-6 w-6 text-primary" />
              </div>
              <div>
                <h1 className="text-2xl font-bold">MQTT Broadcast Dashboard</h1>
                <p className="text-sm text-muted-foreground">
                  Real-time monitoring and analytics
                </p>
              </div>
            </div>
            <div className="flex items-center gap-4">
              {stats && (
                <Badge
                  variant={stats.status === 'running' ? 'success' : 'destructive'}
                >
                  {stats.status === 'running' ? 'Running' : 'Stopped'}
                </Badge>
              )}
              <ThemeToggle />
            </div>
          </div>
        </div>
      </header>

      {/* Navigation Tabs */}
      <Navigation activeTab={activeTab} onTabChange={setActiveTab} />

      {/* Main Content */}
      <main className="container mx-auto px-4 py-6">
        {activeTab === 'docs' ? (
          <DocsPage />
        ) : (
          <>
            {loading && (
              <div className="flex items-center justify-center h-[400px]">
                <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
              </div>
            )}

            {error && (
              <div className="flex items-center justify-center h-[400px]">
                <div className="text-center">
                  <AlertCircle className="h-12 w-12 text-destructive mx-auto mb-4" />
                  <p className="text-lg font-medium">Failed to load dashboard</p>
                  <p className="text-sm text-muted-foreground mt-2">
                    Please check your connection and try again
                  </p>
                </div>
              </div>
            )}

            {stats && (
              <div className="space-y-6">
                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <StatsCard
                    title="Messages/Minute"
                    value={stats.messages.per_minute.toFixed(2)}
                    description={`${stats.messages.last_hour} in last hour`}
                    icon={Zap}
                    variant="success"
                  />
                  <StatsCard
                    title="Active Brokers"
                    value={`${stats.brokers.active}/${stats.brokers.total}`}
                    description={
                      stats.brokers.stale > 0
                        ? `${stats.brokers.stale} stale`
                        : 'All active'
                    }
                    icon={Server}
                    variant={stats.brokers.active > 0 ? 'success' : 'warning'}
                  />
                  <StatsCard
                    title="Memory Usage"
                    value={`${stats.memory.usage_percent}%`}
                    description={`${stats.memory.current_mb} MB / ${stats.memory.threshold_mb} MB`}
                    icon={Database}
                    variant={
                      stats.memory.usage_percent > 100
                        ? 'danger'
                        : stats.memory.usage_percent > 80
                        ? 'warning'
                        : 'default'
                    }
                  />
                  <StatsCard
                    title="Queue Pending"
                    value={stats.queue.pending}
                    description={`${stats.queue.name} queue`}
                    icon={Activity}
                    variant={stats.queue.pending > 100 ? 'warning' : 'default'}
                  />
                </div>

                {/* Throughput Chart */}
                <ThroughputChart period="hour" />

                {/* Brokers Table */}
                <BrokerTable />

                {/* Message Log */}
                {stats.messages.logging_enabled && <MessageLog />}
              </div>
            )}
          </>
        )}
      </main>

      {/* Footer */}
      <footer className="border-t mt-12">
        <div className="container mx-auto px-4 py-4">
          <p className="text-center text-sm text-muted-foreground">
            MQTT Broadcast Dashboard &middot; Auto-refresh every{' '}
            {window.mqttBroadcast.refreshInterval / 1000}s
          </p>
        </div>
      </footer>
    </div>
  );
}
