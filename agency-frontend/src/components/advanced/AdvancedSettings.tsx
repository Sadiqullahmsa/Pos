import React, { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs';
import {
  Button,
} from '@/components/ui/button';
import {
  Input,
} from '@/components/ui/input';
import {
  Label,
} from '@/components/ui/label';
import {
  Switch,
} from '@/components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Progress,
} from '@/components/ui/progress';
import {
  Alert,
  AlertDescription,
  AlertTitle,
} from '@/components/ui/alert';
import {
  Badge,
} from '@/components/ui/badge';
import {
  Separator,
} from '@/components/ui/separator';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  Textarea,
} from '@/components/ui/textarea';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import {
  Settings,
  Cog,
  Database,
  Globe,
  Shield,
  Zap,
  Bell,
  Download,
  Upload,
  RefreshCw,
  Play,
  Pause,
  Square,
  AlertCircle,
  CheckCircle,
  Info,
  Trash2,
  Edit,
  Plus,
  Eye,
  EyeOff,
  Server,
  Activity,
  BarChart3,
  Clock,
  Users,
  DollarSign,
} from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';

interface Setting {
  key: string;
  name: string;
  description: string;
  value: any;
  type: string;
  options?: string[];
  validation_rules?: any[];
  is_public: boolean;
  requires_restart: boolean;
}

interface ExternalApi {
  id: number;
  name: string;
  provider: string;
  category: string;
  status: 'active' | 'inactive' | 'error' | 'maintenance';
  base_url: string;
  is_active: boolean;
  last_health_check?: string;
}

interface SystemInfo {
  php_version: string;
  laravel_version: string;
  database: {
    driver: string;
    version: string;
  };
  server: {
    os: string;
    server_software: string;
    memory_limit: string;
    max_execution_time: string;
  };
  cache: {
    driver: string;
    status: string;
  };
  storage: {
    disk_usage: {
      total: number;
      used: number;
      free: number;
      used_percentage: number;
    };
  };
}

interface ProgressTracker {
  id: string;
  operation: string;
  description: string;
  current_step: number;
  total_steps: number;
  percentage: number;
  status: 'started' | 'in_progress' | 'completed' | 'failed' | 'paused' | 'cancelled';
  elapsed_time: number;
  estimated_completion?: string;
  logs: Array<{
    message: string;
    timestamp: string;
    level: string;
  }>;
  errors: Array<{
    message: string;
    timestamp: string;
    step: number;
  }>;
}

const AdvancedSettings: React.FC = () => {
  const { toast } = useToast();
  const [settings, setSettings] = useState<Record<string, Record<string, Setting>>>({});
  const [externalApis, setExternalApis] = useState<ExternalApi[]>([]);
  const [systemInfo, setSystemInfo] = useState<SystemInfo | null>(null);
  const [progressTrackers, setProgressTrackers] = useState<ProgressTracker[]>([]);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('system');
  const [showPasswords, setShowPasswords] = useState<Record<string, boolean>>({});
  const [editingApi, setEditingApi] = useState<ExternalApi | null>(null);
  const [bulkUpdateMode, setBulkUpdateMode] = useState(false);
  const [selectedSettings, setSelectedSettings] = useState<Record<string, any>>({});

  // Real-time progress tracking
  useEffect(() => {
    const eventSource = new EventSource('/api/progress/stream');
    
    eventSource.onmessage = (event) => {
      const progressData = JSON.parse(event.data);
      setProgressTrackers(prev => {
        const index = prev.findIndex(p => p.id === progressData.id);
        if (index >= 0) {
          const updated = [...prev];
          updated[index] = progressData;
          return updated;
        } else {
          return [...prev, progressData];
        }
      });
    };

    return () => eventSource.close();
  }, []);

  // Load initial data
  useEffect(() => {
    loadSettings();
    loadExternalApis();
    loadSystemInfo();
    loadProgressTrackers();
  }, []);

  const loadSettings = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/settings');
      const data = await response.json();
      
      if (data.success) {
        setSettings(data.data);
      } else {
        toast({
          title: 'Error',
          description: 'Failed to load settings',
          variant: 'destructive',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to load settings',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const loadExternalApis = async () => {
    try {
      const response = await fetch('/api/external-apis');
      const data = await response.json();
      
      if (data.success) {
        setExternalApis(data.data);
      }
    } catch (error) {
      console.error('Failed to load external APIs:', error);
    }
  };

  const loadSystemInfo = async () => {
    try {
      const response = await fetch('/api/settings/system-info');
      const data = await response.json();
      
      if (data.success) {
        setSystemInfo(data.data);
      }
    } catch (error) {
      console.error('Failed to load system info:', error);
    }
  };

  const loadProgressTrackers = async () => {
    try {
      const response = await fetch('/api/progress');
      const data = await response.json();
      
      if (data.success) {
        setProgressTrackers(data.data);
      }
    } catch (error) {
      console.error('Failed to load progress trackers:', error);
    }
  };

  const updateSetting = async (key: string, value: any) => {
    try {
      const response = await fetch(`/api/settings/${encodeURIComponent(key)}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ value }),
      });
      
      const data = await response.json();
      
      if (data.success) {
        toast({
          title: 'Success',
          description: 'Setting updated successfully',
        });
        
        if (data.requires_restart) {
          toast({
            title: 'Restart Required',
            description: 'System restart is required for this change to take effect',
            variant: 'destructive',
          });
        }
        
        loadSettings();
      } else {
        toast({
          title: 'Error',
          description: data.message || 'Failed to update setting',
          variant: 'destructive',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to update setting',
        variant: 'destructive',
      });
    }
  };

  const bulkUpdateSettings = async () => {
    try {
      setLoading(true);
      
      // Create progress tracker
      const progressResponse = await fetch('/api/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          operation: 'Bulk Settings Update',
          description: `Updating ${Object.keys(selectedSettings).length} settings`,
          total_steps: Object.keys(selectedSettings).length + 2,
          category: 'settings',
        }),
      });
      
      const progressData = await progressResponse.json();
      const trackerId = progressData.tracker_id;
      
      // Update progress: starting
      await fetch(`/api/progress/${trackerId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          current_step: 1,
          status: 'in_progress',
          step_description: 'Starting bulk update process',
        }),
      });

      // Perform bulk update
      const response = await fetch('/api/settings/bulk-update', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ settings: selectedSettings }),
      });
      
      const data = await response.json();
      
      // Update progress: completed
      await fetch(`/api/progress/${trackerId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          status: data.success ? 'completed' : 'failed',
          step_description: data.success ? 'Bulk update completed successfully' : 'Bulk update failed',
          log_message: `Updated ${data.summary?.successful || 0} settings, ${data.summary?.failed || 0} failed`,
        }),
      });
      
      if (data.success) {
        toast({
          title: 'Success',
          description: data.message,
        });
        setBulkUpdateMode(false);
        setSelectedSettings({});
        loadSettings();
      } else {
        toast({
          title: 'Error',
          description: data.message || 'Bulk update failed',
          variant: 'destructive',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to perform bulk update',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const testSystemComponents = async () => {
    try {
      setLoading(true);
      
      const response = await fetch('/api/settings/test-system');
      const data = await response.json();
      
      if (data.success) {
        toast({
          title: data.all_tests_passed ? 'All Tests Passed' : 'Some Tests Failed',
          description: data.message,
          variant: data.all_tests_passed ? 'default' : 'destructive',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to test system components',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const clearCache = async () => {
    try {
      setLoading(true);
      
      const response = await fetch('/api/settings/clear-cache', {
        method: 'POST',
      });
      
      const data = await response.json();
      
      if (data.success) {
        toast({
          title: 'Success',
          description: 'System cache cleared successfully',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to clear cache',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const restartServices = async () => {
    try {
      setLoading(true);
      
      const response = await fetch('/api/settings/restart-services', {
        method: 'POST',
      });
      
      const data = await response.json();
      
      if (data.success) {
        toast({
          title: 'Success',
          description: 'System services restarted successfully',
        });
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to restart services',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const renderSettingInput = (setting: Setting) => {
    const key = setting.key;
    const isPassword = setting.type === 'password' || key.includes('password') || key.includes('secret');
    const showPassword = showPasswords[key] || false;

    switch (setting.type) {
      case 'boolean':
        return (
          <Switch
            checked={setting.value}
            onCheckedChange={(checked) => updateSetting(key, checked)}
          />
        );
        
      case 'select':
        return (
          <Select
            value={setting.value}
            onValueChange={(value) => updateSetting(key, value)}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {setting.options?.map((option) => (
                <SelectItem key={option} value={option}>
                  {option}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        );
        
      case 'textarea':
        return (
          <Textarea
            value={setting.value}
            onChange={(e) => updateSetting(key, e.target.value)}
            placeholder={setting.description}
          />
        );
        
      case 'number':
        return (
          <Input
            type="number"
            value={setting.value}
            onChange={(e) => updateSetting(key, Number(e.target.value))}
          />
        );
        
      default:
        return (
          <div className="flex items-center space-x-2">
            <Input
              type={isPassword && !showPassword ? 'password' : 'text'}
              value={setting.value}
              onChange={(e) => updateSetting(key, e.target.value)}
              placeholder={setting.description}
              className="flex-1"
            />
            {isPassword && (
              <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={() => setShowPasswords(prev => ({
                  ...prev,
                  [key]: !prev[key]
                }))}
              >
                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </Button>
            )}
          </div>
        );
    }
  };

  const renderProgressTracker = (tracker: ProgressTracker) => {
    const getStatusColor = (status: string) => {
      switch (status) {
        case 'completed': return 'bg-green-500';
        case 'failed': return 'bg-red-500';
        case 'in_progress': return 'bg-blue-500';
        case 'paused': return 'bg-yellow-500';
        case 'cancelled': return 'bg-gray-500';
        default: return 'bg-gray-400';
      }
    };

    const getStatusIcon = (status: string) => {
      switch (status) {
        case 'completed': return <CheckCircle className="h-4 w-4 text-green-500" />;
        case 'failed': return <AlertCircle className="h-4 w-4 text-red-500" />;
        case 'in_progress': return <RefreshCw className="h-4 w-4 text-blue-500 animate-spin" />;
        case 'paused': return <Pause className="h-4 w-4 text-yellow-500" />;
        case 'cancelled': return <Square className="h-4 w-4 text-gray-500" />;
        default: return <Clock className="h-4 w-4 text-gray-400" />;
      }
    };

    return (
      <Card key={tracker.id} className="mb-4">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              {getStatusIcon(tracker.status)}
              <CardTitle className="text-sm">{tracker.operation}</CardTitle>
              <Badge variant="outline" className={getStatusColor(tracker.status)}>
                {tracker.status}
              </Badge>
            </div>
            <div className="text-sm text-muted-foreground">
              {tracker.percentage}%
            </div>
          </div>
          {tracker.description && (
            <CardDescription>{tracker.description}</CardDescription>
          )}
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            <div className="flex items-center space-x-2 text-sm text-muted-foreground">
              <span>Step {tracker.current_step} of {tracker.total_steps}</span>
              <span>•</span>
              <span>{Math.floor(tracker.elapsed_time)}s elapsed</span>
              {tracker.estimated_completion && (
                <>
                  <span>•</span>
                  <span>ETA: {new Date(tracker.estimated_completion).toLocaleTimeString()}</span>
                </>
              )}
            </div>
            
            <Progress value={tracker.percentage} className="w-full" />
            
            {tracker.logs.length > 0 && (
              <div className="max-h-32 overflow-y-auto bg-gray-50 p-2 rounded text-xs">
                {tracker.logs.slice(-5).map((log, index) => (
                  <div key={index} className="flex items-center space-x-2 mb-1">
                    <span className="text-gray-500">
                      {new Date(log.timestamp).toLocaleTimeString()}
                    </span>
                    <span>{log.message}</span>
                  </div>
                ))}
              </div>
            )}
            
            {tracker.errors.length > 0 && (
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertTitle>Errors</AlertTitle>
                <AlertDescription>
                  {tracker.errors.slice(-1)[0].message}
                </AlertDescription>
              </Alert>
            )}
          </div>
        </CardContent>
      </Card>
    );
  };

  return (
    <div className="container mx-auto p-6">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-3xl font-bold">Advanced Settings</h1>
          <p className="text-muted-foreground">
            Manage system configuration, external APIs, and monitor operations
          </p>
        </div>
        
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            onClick={testSystemComponents}
            disabled={loading}
          >
            <Activity className="h-4 w-4 mr-2" />
            Test System
          </Button>
          
          <Button
            variant="outline"
            onClick={clearCache}
            disabled={loading}
          >
            <RefreshCw className="h-4 w-4 mr-2" />
            Clear Cache
          </Button>
          
          <Button
            variant="outline"
            onClick={restartServices}
            disabled={loading}
          >
            <Server className="h-4 w-4 mr-2" />
            Restart Services
          </Button>
        </div>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList className="grid w-full grid-cols-6">
          <TabsTrigger value="system" className="flex items-center space-x-2">
            <Settings className="h-4 w-4" />
            <span>System</span>
          </TabsTrigger>
          <TabsTrigger value="business" className="flex items-center space-x-2">
            <DollarSign className="h-4 w-4" />
            <span>Business</span>
          </TabsTrigger>
          <TabsTrigger value="security" className="flex items-center space-x-2">
            <Shield className="h-4 w-4" />
            <span>Security</span>
          </TabsTrigger>
          <TabsTrigger value="apis" className="flex items-center space-x-2">
            <Globe className="h-4 w-4" />
            <span>External APIs</span>
          </TabsTrigger>
          <TabsTrigger value="notifications" className="flex items-center space-x-2">
            <Bell className="h-4 w-4" />
            <span>Notifications</span>
          </TabsTrigger>
          <TabsTrigger value="progress" className="flex items-center space-x-2">
            <BarChart3 className="h-4 w-4" />
            <span>Progress</span>
          </TabsTrigger>
        </TabsList>

        {Object.entries(settings).map(([category, categorySettings]) => (
          <TabsContent key={category} value={category}>
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle className="capitalize">{category} Settings</CardTitle>
                    <CardDescription>
                      Configure {category} related settings
                    </CardDescription>
                  </div>
                  
                  <div className="flex items-center space-x-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setBulkUpdateMode(!bulkUpdateMode)}
                    >
                      {bulkUpdateMode ? 'Cancel Bulk' : 'Bulk Edit'}
                    </Button>
                    
                    {bulkUpdateMode && (
                      <Button
                        size="sm"
                        onClick={bulkUpdateSettings}
                        disabled={Object.keys(selectedSettings).length === 0}
                      >
                        Update Selected ({Object.keys(selectedSettings).length})
                      </Button>
                    )}
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-6">
                  {Object.values(categorySettings).map((setting) => (
                    <div key={setting.key} className="space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                          {bulkUpdateMode && (
                            <input
                              type="checkbox"
                              checked={selectedSettings[setting.key] !== undefined}
                              onChange={(e) => {
                                if (e.target.checked) {
                                  setSelectedSettings(prev => ({
                                    ...prev,
                                    [setting.key]: setting.value
                                  }));
                                } else {
                                  setSelectedSettings(prev => {
                                    const updated = { ...prev };
                                    delete updated[setting.key];
                                    return updated;
                                  });
                                }
                              }}
                            />
                          )}
                          
                          <Label htmlFor={setting.key} className="font-medium">
                            {setting.name}
                          </Label>
                          
                          {setting.requires_restart && (
                            <Badge variant="destructive" className="text-xs">
                              Requires Restart
                            </Badge>
                          )}
                          
                          {setting.is_public && (
                            <Badge variant="secondary" className="text-xs">
                              Public
                            </Badge>
                          )}
                        </div>
                        
                        <TooltipProvider>
                          <Tooltip>
                            <TooltipTrigger>
                              <Info className="h-4 w-4 text-muted-foreground" />
                            </TooltipTrigger>
                            <TooltipContent>
                              <p className="max-w-sm">{setting.description}</p>
                            </TooltipContent>
                          </Tooltip>
                        </TooltipProvider>
                      </div>
                      
                      <div className="ml-6">
                        {bulkUpdateMode && selectedSettings[setting.key] !== undefined ? (
                          <Input
                            value={selectedSettings[setting.key]}
                            onChange={(e) => setSelectedSettings(prev => ({
                              ...prev,
                              [setting.key]: e.target.value
                            }))}
                          />
                        ) : (
                          renderSettingInput(setting)
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        ))}

        <TabsContent value="apis">
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>External API Management</CardTitle>
                    <CardDescription>
                      Configure and monitor external API integrations
                    </CardDescription>
                  </div>
                  
                  <Button>
                    <Plus className="h-4 w-4 mr-2" />
                    Add API
                  </Button>
                </div>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Provider</TableHead>
                      <TableHead>Category</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Last Check</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {externalApis.map((api) => (
                      <TableRow key={api.id}>
                        <TableCell className="font-medium">{api.name}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="capitalize">
                            {api.category}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <Badge
                            variant={api.status === 'active' ? 'default' : 'destructive'}
                          >
                            {api.status}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          {api.last_health_check 
                            ? new Date(api.last_health_check).toLocaleString()
                            : 'Never'
                          }
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center space-x-2">
                            <Button variant="outline" size="sm">
                              <Activity className="h-4 w-4" />
                            </Button>
                            <Button variant="outline" size="sm">
                              <Edit className="h-4 w-4" />
                            </Button>
                            <Button variant="outline" size="sm">
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="progress">
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>System Operations Progress</CardTitle>
                <CardDescription>
                  Monitor ongoing system operations and background tasks
                </CardDescription>
              </CardHeader>
              <CardContent>
                {progressTrackers.length === 0 ? (
                  <div className="text-center py-8 text-muted-foreground">
                    No active operations
                  </div>
                ) : (
                  <div className="space-y-4">
                    {progressTrackers.map(renderProgressTracker)}
                  </div>
                )}
              </CardContent>
            </Card>
            
            {systemInfo && (
              <Card>
                <CardHeader>
                  <CardTitle>System Information</CardTitle>
                  <CardDescription>
                    Current system status and resource usage
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">PHP Version</Label>
                      <p className="text-sm text-muted-foreground">{systemInfo.php_version}</p>
                    </div>
                    
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">Laravel Version</Label>
                      <p className="text-sm text-muted-foreground">{systemInfo.laravel_version}</p>
                    </div>
                    
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">Database</Label>
                      <p className="text-sm text-muted-foreground">
                        {systemInfo.database.driver} {systemInfo.database.version}
                      </p>
                    </div>
                    
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">Cache Status</Label>
                      <Badge 
                        variant={systemInfo.cache.status === 'connected' ? 'default' : 'destructive'}
                      >
                        {systemInfo.cache.status}
                      </Badge>
                    </div>
                    
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">Disk Usage</Label>
                      <div className="space-y-1">
                        <Progress value={systemInfo.storage.disk_usage.used_percentage} />
                        <p className="text-xs text-muted-foreground">
                          {systemInfo.storage.disk_usage.used_percentage}% used
                        </p>
                      </div>
                    </div>
                    
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">Memory Limit</Label>
                      <p className="text-sm text-muted-foreground">{systemInfo.server.memory_limit}</p>
                    </div>
                    
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">Server OS</Label>
                      <p className="text-sm text-muted-foreground">{systemInfo.server.os}</p>
                    </div>
                    
                    <div className="space-y-2">
                      <Label className="text-sm font-medium">Server Software</Label>
                      <p className="text-sm text-muted-foreground">{systemInfo.server.server_software}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default AdvancedSettings;