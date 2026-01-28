import {
  BookOpen,
  Terminal,
  ExternalLink,
  AlertCircle,
  CheckCircle2,
  HelpCircle,
  Github,
  Globe
} from 'lucide-react';
import { Card } from './ui/card';

export function DocsPage() {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start gap-4">
        <div className="p-3 bg-primary/10 rounded-lg">
          <BookOpen className="h-8 w-8 text-primary" />
        </div>
        <div>
          <h2 className="text-3xl font-bold">Documentation</h2>
          <p className="text-muted-foreground mt-1">
            Quick reference and helpful resources
          </p>
        </div>
      </div>

      {/* Quick Commands */}
      <Card className="p-6">
        <div className="flex items-center gap-2 mb-4">
          <Terminal className="h-5 w-5 text-primary" />
          <h3 className="text-xl font-semibold">Quick Commands</h3>
        </div>
        <div className="space-y-3">
          <CommandBlock
            title="Start Subscriber"
            command="php artisan mqtt-broadcast"
            description="Start the MQTT subscriber daemon"
          />
          <CommandBlock
            title="Stop Subscriber"
            command="php artisan mqtt-broadcast:terminate"
            description="Gracefully stop all running supervisors"
          />
          <CommandBlock
            title="Check Status"
            command="php artisan tinker"
            code="\enzolarosa\MqttBroadcast\Models\BrokerProcess::all()"
            description="View all active broker processes"
          />
          <CommandBlock
            title="Publish Message"
            command="php artisan tinker"
            code="\enzolarosa\MqttBroadcast\Facades\MqttBroadcast::publish('topic', 'message')"
            description="Publish a message to MQTT broker"
          />
        </div>
      </Card>

      {/* Troubleshooting */}
      <Card className="p-6">
        <div className="flex items-center gap-2 mb-4">
          <HelpCircle className="h-5 w-5 text-primary" />
          <h3 className="text-xl font-semibold">Common Issues</h3>
        </div>
        <div className="space-y-4">
          <TroubleshootItem
            issue="Connection Refused"
            solution="Check if broker is running and firewall allows port 1883"
            command="mosquitto_sub -h 127.0.0.1 -p 1883 -t '#' -v"
          />
          <TroubleshootItem
            issue="No Messages Received"
            solution="Verify topic prefix in config matches published topics"
            hint="If prefix is 'myapp/', publish to 'myapp/topic' not just 'topic'"
          />
          <TroubleshootItem
            issue="High Memory Usage"
            solution="Increase memory threshold or enable auto-restart"
            hint="Config: 'memory' => ['threshold_mb' => 256, 'auto_restart' => true]"
          />
        </div>
      </Card>

      {/* Configuration Check */}
      <Card className="p-6">
        <div className="flex items-center gap-2 mb-4">
          <CheckCircle2 className="h-5 w-5 text-primary" />
          <h3 className="text-xl font-semibold">Configuration Checklist</h3>
        </div>
        <div className="space-y-2">
          <ChecklistItem text="Set MQTT_HOST in .env" />
          <ChecklistItem text="Set MQTT_PORT (default: 1883)" />
          <ChecklistItem text="Configure authentication (if required)" />
          <ChecklistItem text="Set topic prefix (if needed)" />
          <ChecklistItem text="Run migrations: php artisan migrate" />
          <ChecklistItem text="Start subscriber: php artisan mqtt-broadcast" />
        </div>
      </Card>

      {/* External Resources */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <ResourceCard
          icon={Github}
          title="Full Documentation"
          description="Complete guide on GitHub"
          href="https://github.com/enzolarosa/mqtt-broadcast"
        />
        <ResourceCard
          icon={Globe}
          title="Tutorials & Guides"
          description="In-depth tutorials and examples"
          href="https://enzolarosa.dev/docs/mqtt-broadcast"
        />
        <ResourceCard
          icon={BookOpen}
          title="IoT Temperature Monitor"
          description="Complete end-to-end example"
          href="https://enzolarosa.dev/tutorials/iot-temperature-monitoring"
        />
        <ResourceCard
          icon={Github}
          title="Report Issues"
          description="Found a bug? Let us know"
          href="https://github.com/enzolarosa/mqtt-broadcast/issues"
        />
      </div>
    </div>
  );
}

// Helper Components

function CommandBlock({
  title,
  command,
  code,
  description
}: {
  title: string;
  command: string;
  code?: string;
  description: string;
}) {
  return (
    <div className="border rounded-lg p-4 bg-muted/30">
      <div className="flex items-start justify-between mb-2">
        <div>
          <h4 className="font-semibold text-sm">{title}</h4>
          <p className="text-xs text-muted-foreground mt-1">{description}</p>
        </div>
      </div>
      <div className="bg-background rounded-md p-3 font-mono text-sm mt-3 overflow-x-auto">
        <code className="text-primary">{command}</code>
        {code && (
          <>
            <br />
            <code className="text-foreground">&gt;&gt;&gt; {code}</code>
          </>
        )}
      </div>
    </div>
  );
}

function TroubleshootItem({
  issue,
  solution,
  command,
  hint
}: {
  issue: string;
  solution: string;
  command?: string;
  hint?: string;
}) {
  return (
    <div className="border-l-4 border-primary pl-4">
      <div className="flex items-start gap-2">
        <AlertCircle className="h-4 w-4 text-destructive mt-0.5 flex-shrink-0" />
        <div className="space-y-1 flex-1">
          <p className="font-semibold text-sm">{issue}</p>
          <p className="text-sm text-muted-foreground">{solution}</p>
          {command && (
            <div className="bg-muted rounded px-3 py-2 font-mono text-xs mt-2 overflow-x-auto">
              <code>{command}</code>
            </div>
          )}
          {hint && (
            <p className="text-xs text-muted-foreground italic mt-2">
              💡 {hint}
            </p>
          )}
        </div>
      </div>
    </div>
  );
}

function ChecklistItem({ text }: { text: string }) {
  return (
    <div className="flex items-center gap-2">
      <div className="h-4 w-4 rounded-full border-2 border-primary flex items-center justify-center">
        <div className="h-2 w-2 rounded-full bg-primary/30" />
      </div>
      <span className="text-sm">{text}</span>
    </div>
  );
}

function ResourceCard({
  icon: Icon,
  title,
  description,
  href
}: {
  icon: any;
  title: string;
  description: string;
  href: string;
}) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="block group"
    >
      <Card className="p-4 h-full hover:border-primary transition-colors cursor-pointer">
        <div className="flex items-start gap-3">
          <div className="p-2 bg-primary/10 rounded-lg group-hover:bg-primary/20 transition-colors">
            <Icon className="h-5 w-5 text-primary" />
          </div>
          <div className="flex-1">
            <div className="flex items-center gap-2">
              <h4 className="font-semibold">{title}</h4>
              <ExternalLink className="h-3 w-3 text-muted-foreground" />
            </div>
            <p className="text-sm text-muted-foreground mt-1">{description}</p>
          </div>
        </div>
      </Card>
    </a>
  );
}
