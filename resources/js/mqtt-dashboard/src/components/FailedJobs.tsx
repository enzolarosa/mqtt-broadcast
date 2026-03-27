import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useFailedJobs } from '@/hooks/useDashboard';
import { dashboardApi } from '@/lib/api';
import { AlertTriangle, Loader2, RotateCcw, Trash2 } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import type { FailedJob } from '@/types';

export function FailedJobs() {
  const { data: jobs, loading, error, refetch } = useFailedJobs({ limit: 50 });
  const [retrying, setRetrying] = useState<Set<string>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);

  const handleRetry = async (job: FailedJob) => {
    setRetrying((prev) => new Set(prev).add(job.id));
    try {
      await dashboardApi.retryFailedJob(job.id);
      refetch?.();
    } finally {
      setRetrying((prev) => {
        const next = new Set(prev);
        next.delete(job.id);
        return next;
      });
    }
  };

  const handleDelete = async (job: FailedJob) => {
    await dashboardApi.deleteFailedJob(job.id);
    refetch?.();
  };

  const handleRetryAll = async () => {
    setBulkLoading(true);
    try {
      await dashboardApi.retryAllFailedJobs();
      refetch?.();
    } finally {
      setBulkLoading(false);
    }
  };

  const handleFlush = async () => {
    if (!confirm('Delete all failed jobs? This cannot be undone.')) return;
    setBulkLoading(true);
    try {
      await dashboardApi.flushFailedJobs();
      refetch?.();
    } finally {
      setBulkLoading(false);
    }
  };

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5" />
            Failed Jobs
          </CardTitle>
        </CardHeader>
        <CardContent className="flex items-center justify-center h-[300px]">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </CardContent>
      </Card>
    );
  }

  if (error || !jobs) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5" />
            Failed Jobs
          </CardTitle>
        </CardHeader>
        <CardContent className="flex items-center justify-center h-[300px]">
          <p className="text-muted-foreground">Failed to load jobs</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 text-destructive" />
            Failed Jobs
            {jobs.length > 0 && (
              <span className="ml-1 rounded-full bg-destructive px-2 py-0.5 text-xs font-medium text-destructive-foreground">
                {jobs.length}
              </span>
            )}
          </CardTitle>
          {jobs.length > 0 && (
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={handleRetryAll}
                disabled={bulkLoading}
              >
                {bulkLoading ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <RotateCcw className="h-3.5 w-3.5" />
                )}
                <span className="ml-1.5">Retry All</span>
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleFlush}
                disabled={bulkLoading}
                className="text-destructive hover:text-destructive"
              >
                <Trash2 className="h-3.5 w-3.5" />
                <span className="ml-1.5">Flush All</span>
              </Button>
            </div>
          )}
        </div>
      </CardHeader>
      <CardContent>
        {jobs.length === 0 ? (
          <div className="flex items-center justify-center h-[200px]">
            <div className="text-center">
              <AlertTriangle className="h-10 w-10 text-muted-foreground/30 mx-auto mb-3" />
              <p className="text-muted-foreground">No failed jobs</p>
            </div>
          </div>
        ) : (
          <div className="space-y-3 max-h-[500px] overflow-y-auto">
            {jobs.map((job: FailedJob) => (
              <div
                key={job.id}
                className="p-3 rounded-lg border border-destructive/20 bg-destructive/5 hover:bg-destructive/10 transition-colors"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1.5">
                      <span className="text-xs font-mono bg-primary/10 text-primary px-2 py-0.5 rounded">
                        {job.broker}
                      </span>
                      <span className="text-xs text-muted-foreground truncate">
                        {job.topic}
                      </span>
                      {job.retry_count > 0 && (
                        <span className="text-xs text-muted-foreground">
                          {job.retry_count} retr{job.retry_count === 1 ? 'y' : 'ies'}
                        </span>
                      )}
                      <span className="text-xs text-muted-foreground ml-auto whitespace-nowrap">
                        {formatDistanceToNow(new Date(job.failed_at), { addSuffix: true })}
                      </span>
                    </div>

                    {job.message_preview && (
                      <pre className="text-xs font-mono bg-muted/50 p-2 rounded overflow-x-auto mb-1.5">
                        {job.message_preview}
                      </pre>
                    )}

                    <p className="text-xs text-destructive truncate" title={job.exception_preview}>
                      {job.exception_preview}
                    </p>
                  </div>

                  <div className="flex items-center gap-1 shrink-0 ml-2">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleRetry(job)}
                      disabled={retrying.has(job.id)}
                      title="Retry job"
                      className="h-7 w-7 p-0"
                    >
                      {retrying.has(job.id) ? (
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      ) : (
                        <RotateCcw className="h-3.5 w-3.5" />
                      )}
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleDelete(job)}
                      title="Delete job"
                      className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
