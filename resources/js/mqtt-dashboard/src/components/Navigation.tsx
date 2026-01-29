import { Activity, BookOpen } from 'lucide-react';

export type TabType = 'dashboard' | 'docs';

interface NavigationProps {
  activeTab: TabType;
  onTabChange: (tab: TabType) => void;
}

export function Navigation({ activeTab, onTabChange }: NavigationProps) {
  return (
    <nav className="border-b bg-card/50">
      <div className="container mx-auto px-4">
        <div className="flex gap-1">
          <TabButton
            icon={Activity}
            label="Dashboard"
            isActive={activeTab === 'dashboard'}
            onClick={() => onTabChange('dashboard')}
          />
          <TabButton
            icon={BookOpen}
            label="Docs"
            isActive={activeTab === 'docs'}
            onClick={() => onTabChange('docs')}
          />
        </div>
      </div>
    </nav>
  );
}

interface TabButtonProps {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  isActive: boolean;
  onClick: () => void;
}

function TabButton({ icon: Icon, label, isActive, onClick }: TabButtonProps) {
  return (
    <button
      onClick={onClick}
      className={`
        flex items-center gap-2 px-4 py-3 border-b-2 transition-colors
        ${
          isActive
            ? 'border-primary text-foreground font-medium'
            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted'
        }
      `}
    >
      <Icon className="h-4 w-4" />
      <span className="text-sm">{label}</span>
    </button>
  );
}
