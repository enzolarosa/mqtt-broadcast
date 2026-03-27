import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useBrokers } from '@/hooks/useDashboard';
import { Loader2, Server } from 'lucide-react';
import type { Broker } from '@/types';

const statusVariants = {
  connected: 'success' as const,
  idle: 'warning' as const,
  reconnecting: 'warning' as const,
  disconnected: 'destructive' as const,
};

const statusLabels = {
  connected: 'Connected',
  idle: 'Idle',
  reconnecting: 'Reconnecting',
  disconnected: 'Disconnected',
};

export function BrokerTable() {
  const { data: brokers, loading, error } = useBrokers();

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5" />
            Active Brokers
          </CardTitle>
        </CardHeader>
        <CardContent className="flex items-center justify-center h-[200px]">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </CardContent>
      </Card>
    );
  }

  if (error || !brokers) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5" />
            Active Brokers
          </CardTitle>
        </CardHeader>
        <CardContent className="flex items-center justify-center h-[200px]">
          <p className="text-muted-foreground">Failed to load brokers</p>
        </CardContent>
      </Card>
    );
  }

  if (brokers.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5" />
            Active Brokers
          </CardTitle>
        </CardHeader>
        <CardContent className="flex items-center justify-center h-[200px]">
          <p className="text-muted-foreground">No active brokers</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Server className="h-5 w-5" />
          Active Brokers
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b">
                <th className="text-left p-2 font-medium">Name</th>
                <th className="text-left p-2 font-medium">Connection</th>
                <th className="text-left p-2 font-medium">Status</th>
                <th className="text-left p-2 font-medium">Uptime</th>
                <th className="text-right p-2 font-medium">Messages (24h)</th>
              </tr>
            </thead>
            <tbody>
              {brokers.map((broker: Broker) => (
                <tr key={broker.id} className="border-b last:border-0 hover:bg-muted/50">
                  <td className="p-2 font-mono text-xs">{broker.name}</td>
                  <td className="p-2">{broker.connection}</td>
                  <td className="p-2">
                    <Badge variant={statusVariants[broker.connection_status]}>
                      {statusLabels[broker.connection_status]}
                    </Badge>
                  </td>
                  <td className="p-2 text-muted-foreground">
                    {broker.uptime_human}
                  </td>
                  <td className="p-2 text-right font-medium">
                    {broker.messages_24h.toLocaleString()}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  );
}
