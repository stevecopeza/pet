
export interface WorkItem {
  id: string;
  source_type: string;
  source_id: string;
  assigned_user_id: string | null;
  department_id: string;
  priority_score: number;
  status: string;
  sla_time_remaining: number | null;
  due_date: string | null;
  manager_override: number;
  revenue: number;
  client_tier: number;
  signals: WorkItemSignal[];
}

export interface WorkItemSignal {
  type: string;
  severity: string;
  message: string;
  created_at: string;
}

export interface DashboardData {
  overview: {
    activeProjects: number;
    pendingQuotes: number;
    utilizationRate: number;
    revenueThisMonth: number;
  };
  recentActivity: Array<{
    id: number;
    type: string;
    message: string;
    time: string;
  }>;
  skillHeatmap: Array<{
    skill_name: string;
    avg_rating: number;
  }>;
  kpiPerformance: Array<{
    kpi_name: string;
    avg_score: number;
  }>;
}

export interface Project {
  id: number;
  name: string;
  customerId: number;
  soldHours: number;
  soldValue: number;
  state: string;
  startDate?: string;
  endDate?: string;
  malleableData?: Record<string, any>;
  tasks: Task[];
  archivedAt: string | null;
}

export interface Task {
  id: number;
  name: string;
  estimatedHours: number;
  completed: boolean;
}

export interface Quote {
  id: number;
  customerId: number;
  title: string;
  description?: string;
  state: string;
  version: number;
  totalValue: number;
  totalInternalCost?: number;
  adjustedTotalInternalCost?: number;
  margin?: number;
  currency: string;
  acceptedAt?: string;
  lines?: QuoteLine[]; // Deprecated
  components?: QuoteComponent[];
  costAdjustments?: CostAdjustment[];
  malleableData?: Record<string, any>;
}

export interface CostAdjustment {
  id: number;
  description: string;
  amount: number;
  reason: string;
  approvedBy: string;
  appliedAt: string;
}

export interface QuoteComponent {
  id: string;
  type: string;
  section: string;
  description: string;
  sellValue: number;
  internalCost: number;
  items?: {
    description: string;
    quantity: number;
    unitSellPrice: number;
    sellValue: number;
  }[];
}

export interface QuoteLine {
  id: number;
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
  group: string;
}

export interface TimeEntry {
  id: number;
  employeeId: number;
  taskId: number;
  start: string;
  end: string;
  duration: number;
  description: string;
  billable: boolean;
  status: string;
  malleableData?: Record<string, any>;
  createdAt?: string;
  archivedAt?: string | null;
}

export interface Customer {
  id: number;
  name: string;
  legalName?: string;
  contactEmail: string;
  status: string;
  malleableData?: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

export interface Site {
  id: number;
  customerId: number;
  name: string;
  addressLines: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  country: string | null;
  status: string;
  malleableData?: Record<string, any>;
  createdAt?: string;
  archivedAt: string | null;
}

export interface Employee {
  id: number;
  wpUserId: number;
  firstName: string;
  lastName: string;
  email: string;
  status?: string;
  hireDate?: string;
  managerId?: number;
  teamIds?: number[];
  malleableData?: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

export interface Ticket {
  id: number;
  customerId: number;
  siteId?: number;
  slaId?: number;
  subject: string;
  description: string;
  status: string;
  priority: string;
  malleableData?: Record<string, any>;
  createdAt: string;
  updatedAt?: string | null;
  openedAt?: string | null;
  resolvedAt: string | null;
  closedAt?: string | null;
  sla_status?: string;
  response_due_at?: string;
  resolution_due_at?: string;
}

export interface Article {
  id: number;
  title: string;
  content: string;
  category: string;
  status: string;
  createdAt: string;
  updatedAt: string | null;
}

export interface ActivityLog {
  id: number;
  type: string;
  description: string;
  userId: number | null;
  relatedEntityType: string | null;
  relatedEntityId: number | null;
  createdAt: string;
}

export interface Setting {
  key: string;
  value: string;
  type: string;
  description: string;
  updatedAt: string | null;
}

export interface FeedEvent {
  id: string;
  eventType: string;
  sourceEngine: string;
  sourceEntityId: string;
  classification: 'critical' | 'operational' | 'informational' | 'strategic';
  title: string;
  summary: string;
  metadata: Record<string, any>;
  audienceScope: 'global' | 'department' | 'role' | 'user';
  audienceReferenceId: string | null;
  pinned: boolean;
  expiresAt: string | null;
  createdAt: string;
}

export interface Announcement {
  id: string;
  title: string;
  body: string;
  priorityLevel: 'low' | 'normal' | 'high' | 'critical';
  pinned: boolean;
  acknowledgementRequired: boolean;
  gpsRequired: boolean;
  acknowledgementDeadline: string | null;
  audienceScope: 'global' | 'department' | 'role';
  audienceReferenceId: string | null;
  authorUserId: string;
  expiresAt: string | null;
  createdAt: string;
}
